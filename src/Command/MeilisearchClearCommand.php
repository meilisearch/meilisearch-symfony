<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MeilisearchClearCommand extends IndexCommand
{
    protected static $defaultName = 'meilisearch:clear|meili:clear';
    protected static $defaultDescription = 'Clear the index documents';

    public static function getDefaultName(): string
    {
        return self::$defaultName;
    }

    public static function getDefaultDescription(): string
    {
        return self::$defaultDescription;
    }

    protected function configure(): void
    {
        $this->addOption('indices', 'i', InputOption::VALUE_OPTIONAL, 'Comma-separated list of index names');
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

        return Command::SUCCESS;
    }
}
