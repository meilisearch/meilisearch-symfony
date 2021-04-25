<?php

namespace MeiliSearch\Bundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use MeiliSearch\Bundle\Exception\InvalidSettingName;
use MeiliSearch\Bundle\Exception\UpdateException;
use MeiliSearch\Bundle\Model\Aggregator;
use MeiliSearch\Bundle\SearchService;
use MeiliSearch\Client;
use MeiliSearch\Exceptions\ApiException;
use MeiliSearch\Exceptions\TimeOutException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_unique;
use function count;
use function is_subclass_of;
use function method_exists;
use function sprintf;
use function ucfirst;
use const SORT_REGULAR;

/**
 * Class MeiliSearchImportCommand.
 *
 * @package MeiliSearch\Bundle\Command
 */
final class MeiliSearchImportCommand extends IndexCommand
{
    /**
     * @var string
     */
    protected static $defaultName = 'meili:import';

    /** @var Client */
    protected $searchClient;

    /** @var ManagerRegistry */
    protected $managerRegistry;

    /**
     * MeiliSearchImportCommand constructor.
     *
     * @param SearchService   $searchService
     * @param ManagerRegistry $managerRegistry
     * @param Client          $searchClient
     */
    public function __construct(SearchService $searchService, ManagerRegistry $managerRegistry, Client $searchClient)
    {
        parent::__construct($searchService);
        $this->managerRegistry = $managerRegistry;
        $this->searchClient = $searchClient;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Import given entity into search engine')
            ->addOption('indices', 'i', InputOption::VALUE_OPTIONAL, 'Comma-separated list of index names')
            ->addOption(
                'update-settings',
                null,
                InputOption::VALUE_NONE,
                'Update settings related to indices to the search engine'
            );
    }

    /**
     * {@inheritdoc}
     *
     * @throws TimeOutException
     * @throws ApiException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $entitiesToIndex = $this->getEntitiesFromArgs($input, $output);
        $config = $this->searchService->getConfiguration();

        foreach ($entitiesToIndex as $key => $entity) {
            $entityClassName = $entity['name'];
            if (is_subclass_of($entityClassName, Aggregator::class)) {
                unset($entitiesToIndex[$key]);
                $entitiesToIndex = array_merge(
                    $entitiesToIndex,
                    array_map(
                        function ($entity) {
                            return ['name' => $entity];
                        },
                        $entityClassName::getEntities()
                    )
                );
            }
        }

        $entitiesToIndex = array_unique($entitiesToIndex, SORT_REGULAR);

        foreach ($entitiesToIndex as $index => $entity) {
            $entityClassName = $entity['name'];
            if (!$this->searchService->isSearchable($entityClassName)) {
                continue;
            }

            $manager = $this->managerRegistry->getManagerForClass($entityClassName);
            $repository = $manager->getRepository($entityClassName);

            $page = 0;
            do {
                $entities = $repository->findBy(
                    [],
                    null,
                    $config['batchSize'],
                    $config['batchSize'] * $page
                );

                $responses = $this->formatIndexingResponse($this->searchService->index($manager, $entities));
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

                if (!empty($entity['settings'])) {
                    $indexInstance = $this->searchClient->getOrCreateIndex($config['prefix'].$index);
                    foreach ($entity['settings'] as $variable => $value) {
                        $method = sprintf('update%s', ucfirst($variable));
                        if (false === method_exists($indexInstance, $method)) {
                            throw new InvalidSettingName(sprintf('Invalid setting name: "%s"', $variable));
                        }

                        // Update
                        $update = $indexInstance->{$method}($value);

                        // Get Update status from updateID
                        $indexInstance->waitForPendingUpdate($update['updateId']);
                        $updateStatus = $indexInstance->getUpdateStatus($update['updateId']);

                        if ('failed' === $updateStatus['status']) {
                            throw new UpdateException($updateStatus['error']);
                        }
                    }
                }

                ++$page;
            } while (count($entities) >= $config['batchSize']);

            $manager->clear();
        }

        $output->writeln('<info>Done!</info>');

        return 0;
    }

    /**
     * @param array $batch
     *
     * @return array
     *
     * @throws TimeOutException
     */
    private function formatIndexingResponse(array $batch): array
    {
        $formattedResponse = [];

        foreach ($batch as $chunk) {
            foreach ($chunk as $indexName => $apiResponse) {
                if (!array_key_exists($indexName, $formattedResponse)) {
                    $formattedResponse[$indexName] = 0;
                }

                $indexInstance = $this->searchClient->index($indexName);

                // Get Update status from updateID
                $indexInstance->waitForPendingUpdate($apiResponse['updateId']);
                $updateStatus = $indexInstance->getUpdateStatus($apiResponse['updateId']);

                if ('failed' === $updateStatus['status']) {
                    throw new UpdateException($updateStatus['error']);
                }

                $formattedResponse[$indexName] += $updateStatus['type']['number'];
            }
        }

        return $formattedResponse;
    }
}
