<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Command;

use Meilisearch\Bundle\Collection;
use Meilisearch\Exceptions\ApiException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'meilisearch:delete', description: 'Delete the indexes', aliases: ['meili:delete'])]
final class MeilisearchDeleteCommand extends IndexCommand
{
    protected function configure(): void
    {
        $this
            ->addOption('indices', 'i', InputOption::VALUE_OPTIONAL, 'Comma-separated list of index names');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexToDelete = (new Collection($this->getEntitiesFromArgs($input, $output)))->unique('name');

        /** @var array<string, mixed> $index */
        foreach ($indexToDelete as $index) {
            $indexName = $index['prefixed_name'];

            try {
                $this->searchManager->deleteByIndexName($indexName);
            } catch (ApiException $e) {
                $output->writeln('Cannot delete '.$indexName.': '.$e->getMessage());

                continue;
            }

            $output->writeln('Deleted <info>'.$indexName.'</info>');
        }

        if (0 === \count($indexToDelete)) {
            $output->writeln('Cannot delete index. Not found.');
        }

        $output->writeln('<info>Done!</info>');

        return 0;
    }
}
