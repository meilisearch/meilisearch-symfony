<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\EventListener\ConsoleOutputSubscriber;
use Meilisearch\Bundle\Exception\TaskException;
use Meilisearch\Bundle\Model\Aggregator;
use Meilisearch\Bundle\SearchService;
use Meilisearch\Bundle\Services\SettingsUpdater;
use Meilisearch\Client;
use Meilisearch\Exceptions\TimeOutException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class MeilisearchImportCommand extends IndexCommand
{
    private Client $searchClient;
    private ManagerRegistry $managerRegistry;
    private SettingsUpdater $settingsUpdater;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(SearchService $searchService, ManagerRegistry $managerRegistry, Client $searchClient, SettingsUpdater $settingsUpdater, EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct($searchService);

        $this->managerRegistry = $managerRegistry;
        $this->searchClient = $searchClient;
        $this->settingsUpdater = $settingsUpdater;
        $this->eventDispatcher = $eventDispatcher;
    }

    public static function getDefaultName(): string
    {
        return 'meilisearch:import|meili:import';
    }

    public static function getDefaultDescription(): string
    {
        return 'Import given entity into search engine';
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
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED)
            ->addOption(
                'skip-batches',
                null,
                InputOption::VALUE_REQUIRED,
                'Skip the first N batches and start importing from the N+1 batch',
                0
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
        $config = $this->searchService->getConfiguration();
        $updateSettings = $input->getOption('update-settings');
        $batchSize = $input->getOption('batch-size') ?? '';
        $batchSize = ctype_digit($batchSize) ? (int) $batchSize : $config->get('batchSize');
        $responseTimeout = ((int) $input->getOption('response-timeout')) ?: self::DEFAULT_RESPONSE_TIMEOUT;

        /** @var array $index */
        foreach ($entitiesToIndex as $index) {
            $entityClassName = $index['class'];

            if (!$this->searchService->isSearchable($entityClassName)) {
                continue;
            }

            $totalIndexed = 0;

            $manager = $this->managerRegistry->getManagerForClass($entityClassName);
            $repository = $manager->getRepository($entityClassName);
            $classMetadata = $manager->getClassMetadata($entityClassName);
            $entityIdentifiers = $classMetadata->getIdentifierFieldNames();
            $sortByAttrs = array_combine($entityIdentifiers, array_fill(0, \count($entityIdentifiers), 'ASC'));

            $output->writeln('<info>Importing for index '.$entityClassName.'</info>');

            $page = max(0, (int) $input->getOption('skip-batches'));

            if ($page > 0) {
                $output->writeln(
                    \sprintf(
                        '<info>Skipping first <comment>%d</comment> batches (<comment>%d</comment> records)</info>',
                        $page,
                        $page * $batchSize,
                    )
                );
            }

            do {
                $entities = $repository->findBy(
                    [],
                    $sortByAttrs,
                    $batchSize,
                    $batchSize * $page
                );

                $responses = $this->formatIndexingResponse($this->searchService->index($manager, $entities), $responseTimeout);
                $totalIndexed += \count($entities);
                foreach ($responses as $indexName => $numberOfRecords) {
                    $output->writeln(
                        \sprintf(
                            'Indexed a batch of <comment>%d / %d</comment> %s entities into %s index (%d indexed since start)',
                            $numberOfRecords,
                            \count($entities),
                            $entityClassName,
                            '<info>'.$indexName.'</info>',
                            $totalIndexed,
                        )
                    );
                }

                ++$page;
            } while (\count($entities) >= $batchSize);

            $manager->clear();

            if ($updateSettings) {
                $this->settingsUpdater->update($index['prefixed_name'], $responseTimeout);
            }
        }

        $output->writeln('<info>Done!</info>');

        return 0;
    }

    /**
     * @throws TimeOutException
     */
    private function formatIndexingResponse(array $batch, int $responseTimeout): array
    {
        $formattedResponse = [];

        foreach ($batch as $chunk) {
            foreach ($chunk as $indexName => $apiResponse) {
                if (!\array_key_exists($indexName, $formattedResponse)) {
                    $formattedResponse[$indexName] = 0;
                }

                $indexInstance = $this->searchClient->index($indexName);

                // Get task information using uid
                $indexInstance->waitForTask($apiResponse['taskUid'], $responseTimeout);
                $task = $indexInstance->getTask($apiResponse['taskUid']);

                if ('failed' === $task['status']) {
                    throw new TaskException($task['error']['message']);
                }

                $formattedResponse[$indexName] += $task['details']['indexedDocuments'];
            }
        }

        return $formattedResponse;
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
