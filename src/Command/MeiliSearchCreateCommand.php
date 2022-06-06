<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Command;

use MeiliSearch\Bundle\Exception\InvalidSettingName;
use MeiliSearch\Bundle\Exception\TaskException;
use MeiliSearch\Bundle\Model\Aggregator;
use MeiliSearch\Bundle\SearchService;
use MeiliSearch\Client;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MeiliSearchCreateCommand extends IndexCommand
{
    private Client $searchClient;

    public function __construct(SearchService $searchService, Client $searchClient)
    {
        parent::__construct($searchService);

        $this->searchClient = $searchClient;
    }

    public static function getDefaultName(): string
    {
        return 'meili:create';
    }

    public static function getDefaultDescription(): string
    {
        return 'Create indexes';
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::getDefaultDescription())
            ->addOption('indices', 'i', InputOption::VALUE_OPTIONAL, 'Comma-separated list of index names');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexes = $this->getEntitiesFromArgs($input, $output);

        foreach ($indexes as $key => $index) {
            $entityClassName = $index['class'];
            if (is_subclass_of($entityClassName, Aggregator::class)) {
                $indexes->forget($key);

                $indexes = collect(array_merge(
                    $indexes->toArray(),
                    array_map(
                        static fn ($entity) => ['name' => $index['name'], 'class' => $entity],
                        $entityClassName::getEntities()
                    )
                ));
            }
        }

        $entitiesToIndex = array_unique($indexes->toArray(), SORT_REGULAR);

        /** @var array $index */
        foreach ($entitiesToIndex as $index) {
            $entityClassName = $index['class'];

            if (!$this->searchService->isSearchable($entityClassName)) {
                continue;
            }

            $output->writeln('<info>Creating index '.$index['name'].' for '.$entityClassName.'</info>');

            $indexInstance = $this->searchClient->index($index['name']);

            if (isset($index['settings']) && is_array($index['settings'])) {
                foreach ($index['settings'] as $variable => $value) {
                    $method = sprintf('update%s', ucfirst($variable));

                    if (false === method_exists($indexInstance, $method)) {
                        throw new InvalidSettingName(sprintf('Invalid setting name: "%s"', $variable));
                    }

                    $task = $indexInstance->{$method}($value);

                    $indexInstance->waitForTask($task['uid']);
                    $task = $indexInstance->getTask($task['uid']);

                    if ('failed' === $task['status']) {
                        throw new TaskException($task['error']);
                    }
                }
            }
        }

        $output->writeln('<info>Done!</info>');

        return 0;
    }
}
