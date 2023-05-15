<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MeilisearchClearCommand.
 */
final class MeilisearchClearCommand extends IndexCommand
{
    public static function getDefaultName(): string
    {
        return 'meili:clear';
    }

    public static function getDefaultDescription(): string
    {
        return 'Clear the index documents';
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::getDefaultDescription())
            ->addOption('indices', 'i', InputOption::VALUE_OPTIONAL, 'Comma-separated list of index names');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexToClear = $this->getEntitiesFromArgs($input, $output);

        /** @var array<string, mixed> $index */
        foreach ($indexToClear as $index) {
            $indexName = $index['name'];
            $className = $index['class'];
            $msg = "Cleared <info>$indexName</info> index of <comment>$className</comment>";
            $array = $this->searchService->clear($className);

            if ('failed' === $array['status']) {
                $msg = "<error>Index <info>$indexName</info>  couldn\'t be cleared</error>";
            }

            $output->writeln($msg);
        }

        if (0 === count($indexToClear)) {
            $output->writeln('Cannot clear index. Not found.');
        }

        $output->writeln('<info>Done!</info>');

        return 0;
    }
}
