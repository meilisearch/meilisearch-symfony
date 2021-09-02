<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Command;

use MeiliSearch\Exceptions\ApiException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MeiliSearchImportCommand.
 */
final class MeiliSearchDeleteCommand extends IndexCommand
{
    protected static $defaultName = 'meili:delete';

    protected function configure(): void
    {
        $this
            ->setDescription('Delete the indices')
            ->addOption('indices', 'i', InputOption::VALUE_OPTIONAL, 'Comma-separated list of index names');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexToDelete = collect($this->getEntitiesFromArgs($input, $output))->unique('name');

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
