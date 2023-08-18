<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ObjectManager;
use Meilisearch\Bundle\Contracts\SearchQuery;
use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Bundle\Tests\Entity\Comment;
use Meilisearch\Bundle\Tests\Entity\Post;
use Meilisearch\Bundle\Tests\Entity\Tag;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SearchTest extends BaseKernelTestCase
{
    private static string $indexName = 'aggregated';

    protected Connection $connection;
    protected ObjectManager $objectManager;
    protected Application $application;
    protected Indexes $index;

    /**
     * @throws ApiException
     * @throws \Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->get('meilisearch.client');
        $this->objectManager = $this->get('doctrine')->getManager();
        $this->index = $this->client->index($this->getPrefix().self::$indexName);
        $this->application = new Application(self::createKernel());
    }

    /**
     * This test checks the search results on aggregated models.
     * We create models for 'post' and 'tag' using an aggregated 'ContentAggregator' with
     * the index name 'aggregated'.
     */
    public function testSearchImportAggregator(): void
    {
        $testDataTitles = [];

        for ($i = 0; $i < 5; ++$i) {
            $testDataTitles[] = $this->createPost()->getTitle();
        }

        $this->createTag(['id' => 99]);

        $command = $this->application->find('meili:import');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--indices' => $this->index->getUid(),
        ]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Importing for index Meilisearch\Bundle\Tests\Entity\Post', $output);
        $this->assertStringContainsString('Importing for index Meilisearch\Bundle\Tests\Entity\Tag', $output);
        $this->assertStringContainsString('Indexed a batch of '.$i.' / '.$i.' Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__posts index ('.$i.' indexed since start)', $output);
        $this->assertStringContainsString('Indexed a batch of '.$i.' / '.$i.' Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__'.self::$indexName.' index ('.$i.' indexed since start)', $output);
        $this->assertStringContainsString('Indexed a batch of 1 / 1 Meilisearch\Bundle\Tests\Entity\Tag entities into sf_phpunit__tags index (1 indexed since start)', $output);
        $this->assertStringContainsString('Indexed a batch of 1 / 1 Meilisearch\Bundle\Tests\Entity\Tag entities into sf_phpunit__'.self::$indexName.' index (1 indexed since start)', $output);
        $this->assertStringContainsString('Done!', $output);

        $searchTerm = 'Test';

        $results = $this->searchService->search($this->objectManager, Post::class, $searchTerm);
        $this->assertCount(5, $results);

        $resultTitles = array_map(fn (Post $post) => $post->getTitle(), $results);
        $this->assertEqualsCanonicalizing($testDataTitles, $resultTitles);

        $results = $this->searchService->rawSearch(Post::class, $searchTerm);

        $this->assertCount(5, $results['hits']);
        $resultTitles = array_map(fn (array $hit) => $hit['title'], $results['hits']);
        $this->assertEqualsCanonicalizing($testDataTitles, $resultTitles);

        $this->assertCount(5, $results['hits']);
        $this->assertSame(5, $results['nbHits']);

        $results = $this->searchService->search($this->objectManager, Tag::class, $searchTerm);
        $this->assertCount(1, $results);
    }

    public function testSearchPagination(): void
    {
        $testDataTitles = [];

        for ($i = 0; $i < 5; ++$i) {
            $testDataTitles[] = $this->createPost()->getTitle();
        }

        $command = $this->application->find('meili:import');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--indices' => $this->index->getUid(),
        ]);

        $searchTerm = 'Test';

        $results = $this->searchService->search($this->objectManager, Post::class, $searchTerm, ['page' => 2, 'hitsPerPage' => 2]);
        $this->assertCount(2, $results);

        $resultTitles = array_map(fn (Post $post) => $post->getTitle(), $results);
        $this->assertEqualsCanonicalizing(array_slice($testDataTitles, 2, 2), $resultTitles);
    }

    public function testMultiSearch(): void
    {
        $posts = [];
        $comments = [];

        for ($i = 0; $i < 5; ++$i) {
            $post = new Post(['title' => $i < 2 ? "Test post $i" : "Good post $i"]);
            if ($i < 2) {
                $posts[] = $post;
            }

            $this->entityManager->persist($post);

            $comment = new Comment();
            $comment->setPost($post);
            $comment->setContent($i < 2 ? "Test comment $i" : "Good comment $i");
            if ($i < 2) {
                $comments[] = $comment;
            }

            $this->entityManager->persist($comment);
        }

        $this->entityManager->flush();

        $firstTask = $this->client->getTasks()->getResults()[0];
        $this->client->waitForTask($firstTask['uid']);

        $result = $this->searchService->multiSearch($this->entityManager, [
            (new SearchQuery(Post::class))
                ->setQuery('test'),
            (new SearchQuery(Comment::class))
                ->setQuery('test'),
        ]);

        self::assertEquals([
            Post::class => $posts,
            Comment::class => $comments,
        ], $result);
    }
}
