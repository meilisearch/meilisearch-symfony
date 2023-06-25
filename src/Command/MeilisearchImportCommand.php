<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\Exception\InvalidSettingName;
use Meilisearch\Bundle\Exception\TaskException;
use Meilisearch\Bundle\Model\Aggregator;
use Meilisearch\Bundle\SearchService;
use Meilisearch\Bundle\SettingsProvider;
use Meilisearch\Client;
use Meilisearch\Exceptions\TimeOutException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MeilisearchImportCommand extends IndexCommand
{
    private const DEFAULT_RESPONSE_TIMEOUT = 5000;

    protected static $defaultName = 'meili:import';
    protected static $defaultDescription = 'Import given entity into search engine';

    protected Client $searchClient;
    protected ManagerRegistry $managerRegistry;

    public function __construct(SearchService $searchService, ManagerRegistry $managerRegistry, Client $searchClient)
    {
        parent::__construct($searchService);

        $this->managerRegistry = $managerRegistry;
        $this->searchClient = $searchClient;
    }

    protected function configure(): void
    {
        $this
            ->addOption('indices', 'i', InputOption::VALUE_OPTIONAL, 'Comma-separated list of index names')
            ->addOption(
                'update-settings',
                null,
                InputOption::VALUE_NONE,
                'Update settings related to indices to the search engine'
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
        $indexes = $this->getEntitiesFromArgs($input, $output);
        $config = $this->searchService->getConfiguration();

        foreach ($indexes as $key => $index) {
            $entityClassName = $index['class'];
            if (is_subclass_of($entityClassName, Aggregator::class)) {
                $indexes->forget($key);

                $indexes = new Collection(array_merge(
                    $indexes->all(),
                    array_map(
                        fn ($entity) => ['class' => $entity],
                        $entityClassName::getEntities()
                    )
                ));
            }
        }

        $entitiesToIndex = array_unique($indexes->all(), SORT_REGULAR);
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
            $sortByAttrs = array_combine($entityIdentifiers, array_fill(0, count($entityIdentifiers), 'ASC'));

            $output->writeln('<info>Importing for index '.$entityClassName.'</info>');

            $page = max(0, (int) $input->getOption('skip-batches'));

            if ($page > 0) {
                $output->writeln(
                    sprintf(
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
                $totalIndexed += count($entities);
                foreach ($responses as $indexName => $numberOfRecords) {
                    $output->writeln(
                        sprintf(
                            'Indexed a batch of <comment>%d / %d</comment> %s entities into %s index (%d indexed since start)',
                            $numberOfRecords,
                            count($entities),
                            $entityClassName,
                            '<info>'.$indexName.'</info>',
                            $totalIndexed,
                        )
                    );
                }

                if (isset($index['settings'])
                    && is_array($index['settings'])
                    && count($index['settings']) > 0) {
                    $indexInstance = $this->searchClient->index($index['name']);
                    foreach ($index['settings'] as $variable => $value) {
                        $method = sprintf('update%s', ucfirst($variable));

                        if (!method_exists($indexInstance, $method)) {
                            throw new InvalidSettingName(sprintf('Invalid setting name: "%s"', $variable));
                        }

                        if (isset($value['_service']) && $value['_service'] instanceof SettingsProvider) {
                            $value = $value['_service']();
                        }

                        // Update
                        $task = $indexInstance->{$method}($value);

                        // Get task information using uid
                        $indexInstance->waitForTask($task['taskUid'], $responseTimeout);
                        $task = $indexInstance->getTask($task['taskUid']);

                        if ('failed' === $task['status']) {
                            throw new TaskException($task['error']);
                        }

                        $output->writeln('<info>Settings updated of "'.$index['name'].'".</info>');
                    }
                }

                ++$page;
            } while (count($entities) >= $batchSize);

            $manager->clear();
        }

        $output->writeln('<info>Done!</info>');

        return Command::SUCCESS;
    }

    /**
     * @throws TimeOutException
     */
    private function formatIndexingResponse(array $batch, int $responseTimeout): array
    {
        $formattedResponse = [];

        foreach ($batch as $chunk) {
            foreach ($chunk as $indexName => $apiResponse) {
                if (!array_key_exists($indexName, $formattedResponse)) {
                    $formattedResponse[$indexName] = 0;
                }

                $indexInstance = $this->searchClient->index($indexName);

                // Get task information using uid
                $indexInstance->waitForTask($apiResponse['taskUid'], $responseTimeout);
                $task = $indexInstance->getTask($apiResponse['taskUid']);

                if ('failed' === $task['status']) {
                    throw new TaskException($task['error']);
                }

                $formattedResponse[$indexName] += $task['details']['indexedDocuments'];
            }
        }

        return $formattedResponse;
    }
}
