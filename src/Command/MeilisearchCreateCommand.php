<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Command;

use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\EventListener\ConsoleOutputSubscriber;
use Meilisearch\Bundle\Model\Aggregator;
use Meilisearch\Bundle\SearchService;
use Meilisearch\Bundle\Services\SettingsUpdater;
use Meilisearch\Client;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class MeilisearchCreateCommand extends IndexCommand
{
    private Client $searchClient;
    private SettingsUpdater $settingsUpdater;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(SearchService $searchService, Client $searchClient, SettingsUpdater $settingsUpdater, EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct($searchService);

        $this->searchClient = $searchClient;
        $this->settingsUpdater = $settingsUpdater;
        $this->eventDispatcher = $eventDispatcher;
    }

    public static function getDefaultName(): string
    {
        return 'meilisearch:create|meili:create';
    }

    public static function getDefaultDescription(): string
    {
        return 'Create indexes';
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::getDefaultDescription())
            ->addOption('indices', 'i', InputOption::VALUE_OPTIONAL, 'Comma-separated list of index names')
            ->addOption(
                'update-settings',
                null,
                InputOption::VALUE_NEGATABLE,
                'Update settings related to indices to the search engine',
                true
            )
            ->addOption(
                'response-timeout',
                't',
                InputOption::VALUE_REQUIRED,
                'Timeout (in ms) to get response from the search engine',
                self::DEFAULT_RESPONSE_TIMEOUT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->eventDispatcher->addSubscriber(new ConsoleOutputSubscriber(new SymfonyStyle($input, $output)));

        $indexes = $this->getEntitiesFromArgs($input, $output);
        $entitiesToIndex = $this->entitiesToIndex($indexes);
        $updateSettings = $input->getOption('update-settings');
        $responseTimeout = ((int) $input->getOption('response-timeout')) ?: self::DEFAULT_RESPONSE_TIMEOUT;

        /** @var array $index */
        foreach ($entitiesToIndex as $index) {
            $entityClassName = $index['class'];

            if (!$this->searchService->isSearchable($entityClassName)) {
                continue;
            }

            $indexName = $index['prefixed_name'];

            $output->writeln('<info>Creating index '.$indexName.' for '.$entityClassName.'</info>');

            $task = $this->searchClient->createIndex($indexName);
            $this->searchClient->waitForTask($task['taskUid'], $responseTimeout);

            if ($updateSettings) {
                $this->settingsUpdater->update($indexName, $responseTimeout);
            }
        }

        $output->writeln('<info>Done!</info>');

        return 0;
    }

    private function entitiesToIndex(Collection $indexes): array
    {
        foreach ($indexes as $key => $index) {
            $entityClassName = $index['class'];

            if (!is_subclass_of($entityClassName, Aggregator::class)) {
                continue;
            }

            $indexes->forget($key);

            $indexes = new Collection(array_merge(
                $indexes->all(),
                array_map(
                    static fn ($entity) => ['name' => $index['name'], 'prefixed_name' => $index['prefixed_name'], 'class' => $entity],
                    $entityClassName::getEntities()
                )
            ));
        }

        return array_unique($indexes->all(), SORT_REGULAR);
    }
}
