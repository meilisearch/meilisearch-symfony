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
 * Class SearchTest.
 */
class SearchTest extends BaseTest
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
     * {@inheritDoc}
     *
     * @throws ApiException
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

    public function postProvider()
    {
        return [
            'post-contents differ from object-ID' => [
                [
                    [
                        'title' => 'Test',
                        'content' => 'Test content',
                        'published_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    ],
                    [
                        'title' => 'Test2',
                        'content' => 'Test content2',
                        'published_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    ],
                    [
                        'title' => 'Test3',
                        'content' => 'Test content3',
                        'published_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    ],
                ],
            ],
            'post-contents match object-ID' => [
                [
                    [
                        'title' => 'Test',
                        'content' => 1,
                        'published_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    ],
                    [
                        'title' => 'Test2',
                        'content' => 1,
                        'published_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    ],
                    [
                        'title' => 'Test3',
                        'content' => 1,
                        'published_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider postProvider
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function testSearchImportAggregator(array $posts)
    {
        $nbEntityIndexed = 0;
        // START - Insertion Part took from CommandsTest class
        foreach ($posts as $post) {
            $this->connection->insert(
                $this->indexName,
                $post
            );
            ++$nbEntityIndexed;
        }

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
        // END - Insertion Part took from CommandsTest class

        // Test searchService
        $searchTerm = 'test';
        $results = $this->searchService->search($this->objectManager, Post::class, $searchTerm);
        $this->assertCount($nbEntityIndexed, $results);

        $testDataTitles = array_map(fn (array $post) => $post['title'], $posts);
        $resultTitles = array_map(fn (Post $post) => $post->getTitle(), $results);
        $this->assertEqualsCanonicalizing($testDataTitles, $resultTitles);

        $results = $this->searchService->rawSearch(Post::class, $searchTerm);
        $this->assertCount($nbEntityIndexed, $results['hits']);
        $resultTitles = array_map(fn (array $hit) => $hit['title'], $results['hits']);
        $this->assertEqualsCanonicalizing($testDataTitles, $resultTitles);

        // clearup table
        $this->connection->executeStatement($this->platform->getTruncateTableSQL($this->indexName, true));
        $this->cleanUp();
    }
}
