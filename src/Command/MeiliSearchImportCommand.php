<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use MeiliSearch\Bundle\Exception\InvalidSettingName;
use MeiliSearch\Bundle\Exception\TaskException;
use MeiliSearch\Bundle\Model\Aggregator;
use MeiliSearch\Bundle\SearchService;
// use MeiliSearch\Bundle\CollectionXX;
use MeiliSearch\Client;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MeiliSearchImportCommand.
 */
final class MeiliSearchImportCommand extends IndexCommand
{
    private const DEFAULT_RESPONSE_TIMEOUT = 5000;

    protected Client $searchClient;
    protected ManagerRegistry $managerRegistry;

    public function __construct(SearchService $searchService, ManagerRegistry $managerRegistry, Client $searchClient)
    {
        parent::__construct($searchService);

        $this->managerRegistry = $managerRegistry;
        $this->searchClient = $searchClient;
    }

    public static function getDefaultName(): string
    {
        return 'meili:import';
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
                InputOption::VALUE_NONE,
                'Update settings related to indices to the search engine'
            )
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED)
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

                $indexes = new CollectionXX(array_merge(
                    $indexes->toArray(),
                    array_map(
                        fn ($entity) => ['class' => $entity],
                        $entityClassName::getEntities()
                    )
                ));
            }
        }

        $entitiesToIndex = array_unique($indexes->toArray(), SORT_REGULAR);
        $batchSize = $input->getOption('batch-size');
        $batchSize = ctype_digit($batchSize) ? (int) $batchSize : $config->get('batchSize');
        $responseTimeout = ((int) $input->getOption('response-timeout')) ?: self::DEFAULT_RESPONSE_TIMEOUT;

        /** @var array $index */
        foreach ($entitiesToIndex as $index) {
            $entityClassName = $index['class'];
            if (!$this->searchService->isSearchable($entityClassName)) {
                continue;
            }

            $manager = $this->managerRegistry->getManagerForClass($entityClassName);
            $repository = $manager->getRepository($entityClassName);

            $output->writeln('<info>Importing for index '.$entityClassName.'</info>');

            $page = 0;
            do {
                $entities = $repository->findBy(
                    [],
                    null,
                    $batchSize,
                    $batchSize * $page
                );

                $responses = $this->formatIndexingResponse($this->searchService->index($manager, $entities), $responseTimeout);
                foreach ($responses as $indexName => $numberOfRecords) {
                    $output->writeln(
                        sprintf(
                            'Indexed <comment>%s / %s</comment> %s entities into %s index',
                            $numberOfRecords,
                            count($entities),
                            $entityClassName,
                            '<info>'.$indexName.'</info>'
                        )
                    );
                }

                if (isset($index['settings'])
                    && is_array($index['settings'])
                    && count($index['settings']) > 0) {
                    $indexInstance = $this->searchClient->index($index['name']);
                    foreach ($index['settings'] as $variable => $value) {
                        $method = sprintf('update%s', ucfirst($variable));
                        if (false === method_exists($indexInstance, $method)) {
                            throw new InvalidSettingName(sprintf('Invalid setting name: "%s"', $variable));
                        }

                        // Update
                        $task = $indexInstance->{$method}($value);

                        // Get task information using uid
                        $indexInstance->waitForTask($task['taskUid'], $responseTimeout);
                        $task = $indexInstance->getTask($task['taskUid']);

                        if ('failed' === $task['status']) {
                            throw new TaskException($task['error']);
                        } else {
                            $output->writeln('<info>Settings updated.</info>');
                        }
                    }
                }

                ++$page;
            } while (count($entities) >= $batchSize);

            $manager->clear();
        }

        $output->writeln('<info>Done!</info>');

        return 0;
    }

    /*
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
