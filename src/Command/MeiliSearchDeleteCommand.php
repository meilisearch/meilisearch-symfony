<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Command;

use MeiliSearch\Bundle\Collection;
use MeiliSearch\Exceptions\ApiException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MeiliSearchDeleteCommand.
 */
final class MeiliSearchDeleteCommand extends IndexCommand
{
    public static function getDefaultName(): string
    {
        return 'meili:delete';
    }

    public static function getDefaultDescription(): string
    {
        return 'Delete the indexes';
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::getDefaultDescription())
            ->addOption('indices', 'i', InputOption::VALUE_OPTIONAL, 'Comma-separated list of index names');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexToDelete = (new Collection($this->getEntitiesFromArgs($input, $output)))->unique('name');

        /** @var array<string, mixed> $index */
        foreach ($indexToDelete as $index) {
            $indexName = $index['name'];
            try {
                $this->searchService->deleteByIndexName($indexName);
            } catch (ApiException $e) {
                $output->writeln('Cannot delete '.$indexName.': '.$e->getMessage());
                continue;
            }
            $output->writeln('Deleted <info>'.$indexName.'</info>');
        }

        if (0 === count($indexToDelete)) {
            $output->writeln('Cannot delete index. Not found.');
        }

        $output->writeln('<info>Done!</info>');

        return 0;
    }
}
