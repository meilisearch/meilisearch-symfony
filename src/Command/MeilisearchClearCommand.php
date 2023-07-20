<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'meilisearch:clear', description: 'Clear the index documents', aliases: ['meili:clear'])]
final class MeilisearchClearCommand extends IndexCommand
{
    protected function configure(): void
    {
        $this
            ->addOption('indices', 'i', InputOption::VALUE_OPTIONAL, 'Comma-separated list of index names');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexToClear = $this->getEntitiesFromArgs($input, $output);

        /** @var array<string, mixed> $index */
        foreach ($indexToClear as $index) {
            $indexName = $index['prefixed_name'];
            $className = $index['class'];
            $msg = "Cleared <info>$indexName</info> index of <comment>$className</comment>";
            $array = $this->searchManager->clear($className);

            if ('failed' === $array['status']) {
                $msg = "<error>Index <info>$indexName</info>  couldn\'t be cleared</error>";
            }

            $output->writeln($msg);
        }

        if (0 === \count($indexToClear)) {
            $output->writeln('Cannot clear index. Not found.');
        }

        $output->writeln('<info>Done!</info>');

        return 0;
    }
}
