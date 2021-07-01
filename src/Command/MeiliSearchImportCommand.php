<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use MeiliSearch\Bundle\Exception\InvalidSettingName;
use MeiliSearch\Bundle\Exception\UpdateException;
use MeiliSearch\Bundle\Model\Aggregator;
use MeiliSearch\Bundle\SearchService;
use MeiliSearch\Client;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MeiliSearchImportCommand.
 */
final class MeiliSearchImportCommand extends IndexCommand
{
    protected static $defaultName = 'meili:import';
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
            ->setDescription('Import given entity into search engine')
            ->addOption('indices', 'i', InputOption::VALUE_OPTIONAL, 'Comma-separated list of index names')
            ->addOption(
                'update-settings',
                null,
                InputOption::VALUE_NONE,
                'Update settings related to indices to the search engine'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexes = $this->getEntitiesFromArgs($input, $output);
        $config = $this->searchService->getConfiguration();

        foreach ($indexes as $key => $index) {
            $entityClassName = $index['class'];
            if (is_subclass_of($entityClassName, Aggregator::class)) {
                $indexes->forget($key);

                $indexes = collect(array_merge(
                    $indexes->toArray(),
                    array_map(
                        fn ($entity) => ['class' => $entity],
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

            $manager = $this->managerRegistry->getManagerForClass($entityClassName);
            $repository = $manager->getRepository($entityClassName);

            $output->writeln('<info>Importing for index '.$entityClassName.'</info>');

            $page = 0;
            do {
                $entities = $repository->findBy(
                    [],
                    null,
                    $config->get('batchSize'),
                    $config->get('batchSize') * $page
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

                if (isset($index['settings'])
                    && is_array($index['settings'])
                    && count($index['settings']) > 0) {
                    $indexInstance = $this->searchClient->getOrCreateIndex($index['name']);
                    foreach ($index['settings'] as $variable => $value) {
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
                        } else {
                            $output->writeln('<info>Settings updated.</info>');
                        }
                    }
                }

                ++$page;
            } while (count($entities) >= $config->get('batchSize'));

            $manager->clear();
        }

        $output->writeln('<info>Done!</info>');

        return 0;
    }

    /*
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
