<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Test\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ObjectManager;
use MeiliSearch\Bundle\Test\BaseKernelTestCase;
use MeiliSearch\Bundle\Test\Entity\Post;
use MeiliSearch\Bundle\Test\Entity\Tag;
use MeiliSearch\Client;
use MeiliSearch\Endpoints\Indexes;
use MeiliSearch\Exceptions\ApiException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class SearchTest.
 */
class SearchTest extends BaseKernelTestCase
{
    private static string $indexName = 'aggregated';

    protected Client $client;
    protected Connection $connection;
    protected ObjectManager $objectManager;
    protected Application $application;
    protected Indexes $index;

    /**
     * {@inheritDoc}
     *
     * @throws ApiException
     * @throws \Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->get('search.client');
        $this->objectManager = $this->get('doctrine')->getManager();
        $this->index = $this->client->getOrCreateIndex($this->getPrefix().self::$indexName);
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

        $this->assertStringContainsString('Importing for index MeiliSearch\Bundle\Test\Entity\Post', $output);
        $this->assertStringContainsString('Importing for index MeiliSearch\Bundle\Test\Entity\Tag', $output);
        $this->assertStringContainsString('Indexed '.$i.' / '.$i.' MeiliSearch\Bundle\Test\Entity\Post entities into sf_phpunit__posts index', $output);
        $this->assertStringContainsString('Indexed '.$i.' / '.$i.' MeiliSearch\Bundle\Test\Entity\Post entities into sf_phpunit__'.self::$indexName.' index', $output);
        $this->assertStringContainsString('Indexed 1 / 1 MeiliSearch\Bundle\Test\Entity\Tag entities into sf_phpunit__tags index', $output);
        $this->assertStringContainsString('Indexed 1 / 1 MeiliSearch\Bundle\Test\Entity\Tag entities into sf_phpunit__'.self::$indexName.' index', $output);
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

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
