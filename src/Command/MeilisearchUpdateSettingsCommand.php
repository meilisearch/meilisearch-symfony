<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Command;

use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\EventListener\ConsoleOutputSubscriber;
use Meilisearch\Bundle\Model\Aggregator;
use Meilisearch\Bundle\SearchService;
use Meilisearch\Bundle\Services\SettingsUpdater;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class MeilisearchUpdateSettingsCommand extends IndexCommand
{
    private SettingsUpdater $settingsUpdater;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(SearchService $searchService, SettingsUpdater $settingsUpdater, EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct($searchService);

        $this->settingsUpdater = $settingsUpdater;
        $this->eventDispatcher = $eventDispatcher;
    }

    public static function getDefaultName(): string
    {
        return 'meilisearch:update-settings';
    }

    public static function getDefaultDescription(): string
    {
        return 'Push settings to meilisearch';
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::getDefaultDescription())
            ->addOption('indices', 'i', InputOption::VALUE_OPTIONAL, 'Comma-separated list of index names')
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
        $responseTimeout = ((int) $input->getOption('response-timeout')) ?: self::DEFAULT_RESPONSE_TIMEOUT;

        /** @var array $index */
        foreach ($entitiesToIndex as $index) {
            $entityClassName = $index['class'];

            if (!$this->searchService->isSearchable($entityClassName)) {
                continue;
            }

            $this->settingsUpdater->update($index['prefixed_name'], $responseTimeout);
        }

        $output->writeln('<info>Done!</info>');

        return 0;
    }

    private function entitiesToIndex($indexes): array
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
