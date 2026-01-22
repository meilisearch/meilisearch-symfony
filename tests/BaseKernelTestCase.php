<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\SearchManagerInterface;
use Meilisearch\Client;
use Meilisearch\Contracts\Task;
use Meilisearch\Contracts\TasksQuery;
use Meilisearch\Exceptions\ApiException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function Meilisearch\partial;

abstract class BaseKernelTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;
    protected Client $client;
    protected SearchManagerInterface $searchManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = $this->get('doctrine.orm.entity_manager');
        $this->client = $this->get('meilisearch.client');
        $this->searchManager = $this->get('meilisearch.manager');

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        $tool = new SchemaTool($this->entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $this->cleanUp();
    }

    protected function getPrefix(): string
    {
        return $this->searchManager->getConfiguration()->get('prefix');
    }

    protected function get(string $id): ?object
    {
        return self::getContainer()->get($id);
    }

    protected function waitForAllTasks(): void
    {
        $query = (new TasksQuery())->setStatuses(['enqueued', 'processing']);

        foreach ($this->client->getTasks($query) as $task) {
            if (\is_array($task)) {
                $http = (new \ReflectionObject($this->client))->getProperty('http')->getValue($this->client);
                $task = Task::fromArray($task, partial(Engine::waitTask(...), $http));
            }
            $task->wait();
        }
    }

    private function cleanUp(): void
    {
        (new Collection($this->searchManager->getConfiguration()->get('indices')))
                ->each(function ($item): bool {
                    $this->cleanupIndex($item['prefixed_name']);

                    return true;
                });
    }

    private function cleanupIndex(string $indexName): void
    {
        try {
            $this->searchManager->deleteByIndexName($indexName);
        } catch (ApiException) {
            // Don't assert undefined indexes.
            // Just plainly delete all existing indexes to get a clean state.
        }
    }
}
