<?php

namespace MeiliSearch\Bundle\Test\TestCase;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\Persistence\ObjectManager;
use Exception;
use MeiliSearch\Bundle\Services\MeiliSearchService;
use MeiliSearch\Bundle\Test\BaseTest;
use MeiliSearch\Bundle\Test\Entity\Comment;
use MeiliSearch\Bundle\Test\Entity\ContentAggregator;
use MeiliSearch\Bundle\Test\Entity\Post;
use MeiliSearch\Client;
use MeiliSearch\Endpoints\Indexes;
use MeiliSearch\Exceptions\HTTPRequestException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class SearchTest
 *
 * @package MeiliSearch\Bundle\Test\TestCase
 */
class SearchTest extends BaseTest
{

    /** @var MeiliSearchService $searchService */
    protected $searchService;

    /** @var Client $client */
    protected $client;

    /** @var ObjectManager $objectManager */
    protected $objectManager;

    /** @var Connection $connection */
    protected $connection;

    /** @var Application $application */
    protected $application;

    /** @var string $indexName */
    protected $indexName;

    /** @var AbstractPlatform|null $platform */
    protected $platform;

    /** @var Indexes $index */
    protected $index;

    /**
     * @inheritDoc
     * @throws DBALException
     * @throws HTTPRequestException
     * @throws Exception
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->searchService = $this->get('search.service');
        $this->client        = $this->get('search.client');
        $this->objectManager = $this->get('doctrine')->getManager();
        $this->connection    = $this->get('doctrine')->getConnection();
        $this->platform      = $this->connection->getDatabasePlatform();
        $this->indexName     = 'posts';
        $this->index         = $this->client->getOrCreateIndex($this->getPrefix() . $this->indexName);

        $this->application = new Application(self::$kernel);
        $this->refreshDb($this->application);
    }

    public function cleanUp()
    {
        try {
            $this->searchService->delete(Post::class);
            $this->searchService->delete(Comment::class);
            $this->searchService->delete(ContentAggregator::class);
        } catch (HTTPRequestException $e) {
            $this->assertEquals('Index sf_phpunit__comments not found', $e->getMessage());
        }
    }

    public function testSearchImportAggregator()
    {
        $nbEntityIndexed = 0;
        // START - Insertion Part took from CommandsTest class
        $now = new \DateTime();
        $this->connection->insert(
            $this->indexName,
            [
                'title'        => 'Test',
                'content'      => 'Test content',
                'published_at' => $now->format('Y-m-d H:i:s'),
            ]
        );
        $nbEntityIndexed++;

        $this->connection->insert(
            $this->indexName,
            [
                'title'        => 'Test2',
                'content'      => 'Test content2',
                'published_at' => $now->format('Y-m-d H:i:s'),
            ]
        );
        $nbEntityIndexed++;

        $this->connection->insert(
            $this->indexName,
            [
                'title'        => 'Test3',
                'content'      => 'Test content3',
                'published_at' => $now->format('Y-m-d H:i:s'),
            ]
        );
        $nbEntityIndexed++;


        $command       = $this->application->find('meili:import');
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'command'   => $command->getName(),
                '--indices' => 'contents',
            ]
        );

        // Checks output
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Done!', $output);
        // END - Insertion Part took from CommandsTest class

        // Test searchService
        $searchTerm = 'test';
        $results = $this->searchService->search($this->objectManager, Post::class, $searchTerm);
        $this->assertCount($nbEntityIndexed , $results);

        // clearup table
        $this->connection->executeUpdate($this->platform->getTruncateTableSQL($this->indexName, true));
        $this->cleanUp();
    }
}
