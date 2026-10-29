<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Command;

use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\SearchManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @phpstan-import-type IndexStats from Engine
 * @phpstan-import-type Stats from Engine
 */
#[AsCommand(name: 'meilisearch:stats', description: 'Show the database and index stats', aliases: ['meili:stats'])]
final class MeilisearchStatsCommand extends IndexCommand
{
    private const BYTE_UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];
    private const COMPACT_RECOMMENDATION_RATIO = 0.3;

    public function __construct(
        SearchManagerInterface $searchManager,
        private readonly Engine $engine,
    ) {
        parent::__construct($searchManager);
    }

    protected function configure(): void
    {
        $this
            ->addOption('indices', 'i', InputOption::VALUE_OPTIONAL, 'Comma-separated list of index names');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexes = $this->getEntitiesFromArgs($input, $output)->unique('prefixed_name');
        $stats = $this->engine->stats();

        $this->renderGlobalStats($stats, $output);
        $this->renderIndexesStats($indexes, $stats['indexes'], $output);

        if ($this->fragmentationRatio($stats) > self::COMPACT_RECOMMENDATION_RATIO) {
            $output->writeln('<comment>Fragmentation ratio is above 30%, compacting your indexes may improve performance. Run the meilisearch:compact command.</comment>');
        }

        return 0;
    }

    /**
     * @param Stats $stats
     */
    private function renderGlobalStats(array $stats, OutputInterface $output): void
    {
        (new Table($output))
            ->setHeaderTitle('Global stats')
            ->setHeaders(['Database size', 'Used database size', 'Fragmentation ratio', 'Last update'])
            ->addRow([
                $this->formatBytes($stats['databaseSize']),
                $this->formatBytes($stats['usedDatabaseSize']),
                $this->formatPercentage($this->fragmentationRatio($stats)),
                $this->formatLastUpdate($stats['lastUpdate']),
            ])
            ->render();
    }

    /**
     * @param array<non-empty-string, IndexStats> $indexesStats
     */
    private function renderIndexesStats(Collection $indexes, array $indexesStats, OutputInterface $output): void
    {
        $table = (new Table($output))
            ->setHeaderTitle('Indexes stats')
            ->setHeaders([
                'Index',
                'No of documents',
                'Document db size',
                'Avg document size',
                'Indexing',
                'No of embeddings',
                'No of embedded documents',
            ]);

        /** @var array<string, mixed> $index */
        foreach ($indexes as $index) {
            $indexName = $index['prefixed_name'];

            if (!isset($indexesStats[$indexName])) {
                continue;
            }

            $indexStats = $indexesStats[$indexName];

            $table->addRow([
                $indexName,
                number_format($indexStats['numberOfDocuments']),
                $this->formatBytes($indexStats['rawDocumentDbSize']),
                $this->formatBytes($indexStats['avgDocumentSize']),
                $indexStats['isIndexing'] ? 'Yes' : 'No',
                number_format($indexStats['numberOfEmbeddings']),
                number_format($indexStats['numberOfEmbeddedDocuments']),
            ]);
        }

        $table->render();
    }

    /**
     * @param Stats $stats
     */
    private function fragmentationRatio(array $stats): float
    {
        if (0 === $stats['databaseSize']) {
            return 0.0;
        }

        return ($stats['databaseSize'] - $stats['usedDatabaseSize']) / $stats['databaseSize'];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $size = $bytes;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < \count(self::BYTE_UNITS) - 1) {
            $size /= 1024;
            ++$unitIndex;
        }

        return number_format($size, 2).' '.self::BYTE_UNITS[$unitIndex];
    }

    private function formatPercentage(float $ratio): string
    {
        return number_format($ratio * 100, 2).'%';
    }

    private function formatLastUpdate(?string $lastUpdate): string
    {
        if (null === $lastUpdate) {
            return '-';
        }

        return (new \DateTimeImmutable($lastUpdate))->format('Y M d, H:i:s.u');
    }
}
