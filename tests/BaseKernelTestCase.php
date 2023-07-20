<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\SearchManagerInterface;
use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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

        $metaData = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->entityManager);
        $tool->dropSchema($metaData);
        $tool->createSchema($metaData);

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
        foreach ($this->client->getTasks() as $task) {
            $this->client->waitForTask($task['uid']);
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
        } catch (ApiException $e) {
            // Don't assert undefined indexes.
            // Just plainly delete all existing indexes to get a clean state.
        }
    }
}
