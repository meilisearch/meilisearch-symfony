<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Test\TestCase;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\Persistence\ObjectManager;
use MeiliSearch\Bundle\Services\MeiliSearchService;
use MeiliSearch\Bundle\Test\BaseTest;
use MeiliSearch\Bundle\Test\Entity\Comment;
use MeiliSearch\Bundle\Test\Entity\ContentAggregator;
use MeiliSearch\Bundle\Test\Entity\Post;
use MeiliSearch\Client;
use MeiliSearch\Endpoints\Indexes;
use MeiliSearch\Exceptions\ApiException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class CommandsTest.
 */
class CommandsTest extends BaseTest
{
    protected MeiliSearchService $searchService;
    protected Client $client;
    protected ObjectManager $objectManager;
    protected Connection $connection;
    protected Application $application;
    protected string $indexName;
    protected ?AbstractPlatform $platform;
    protected Indexes $index;

    /**
     * @throws ApiException
     * @throws \Doctrine\DBAL\Exception
     * @throws \Exception
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->searchService = $this->get('search.service');
        $this->client = $this->get('search.client');
        $this->objectManager = $this->get('doctrine')->getManager();
        $this->connection = $this->get('doctrine')->getConnection();
        $this->platform = $this->connection->getDatabasePlatform();
        $this->indexName = 'posts';
        $this->index = $this->client->getOrCreateIndex($this->getPrefix().$this->indexName);

        $this->application = new Application(self::$kernel);
        $this->refreshDb($this->application);
    }

    public function cleanUp()
    {
        try {
            $this->searchService->delete(Post::class);
            $this->searchService->delete(Comment::class);
            $this->searchService->delete(ContentAggregator::class);
        } catch (ApiException $e) {
            $this->assertEquals('Index sf_phpunit__comments not found', $e->getMessage());
        }
    }

    public function testSearchClearUnknownIndex()
    {
        $unknownIndexName = 'test';

        $command = $this->application->find('meili:clear');
        $commandTester = new CommandTester($command);

        $commandTester->execute(
            [
                'command' => $command->getName(),
                '--indices' => $unknownIndexName,
            ]
        );

        // Checks output and ensure it failed
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No index named '.$unknownIndexName, $output);
        $this->cleanUp();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function testSearchImportAggregator()
    {
        $now = new \DateTime();
        $this->connection->insert(
            $this->indexName,
            [
                'title' => 'Test',
                'content' => 'Test content',
                'published_at' => $now->format('Y-m-d H:i:s'),
            ]
        );
        $this->connection->insert(
            $this->indexName,
            [
                'title' => 'Test2',
                'content' => 'Test content2',
                'published_at' => $now->format('Y-m-d H:i:s'),
            ]
        );
        $this->connection->insert(
            $this->indexName,
            [
                'title' => 'Test3',
                'content' => 'Test content3',
                'published_at' => $now->format('Y-m-d H:i:s'),
            ]
        );

        $command = $this->application->find('meili:import');
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'command' => $command->getName(),
                '--indices' => 'contents',
            ]
        );

        // Checks output
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Done!', $output);

        // clearup table
        $this->connection->executeStatement($this->platform->getTruncateTableSQL($this->indexName, true));
        $this->cleanUp();
    }
}
