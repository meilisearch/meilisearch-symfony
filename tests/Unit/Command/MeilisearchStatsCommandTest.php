<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit\Command;

use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\Command\MeilisearchStatsCommand;
use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\SearchManagerInterface;
use Meilisearch\Bundle\Tests\Entity\Comment;
use Meilisearch\Bundle\Tests\Entity\Link;
use Meilisearch\Bundle\Tests\Entity\Post;
use Meilisearch\Bundle\Tests\Entity\Tag;
use Meilisearch\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MeilisearchStatsCommandTest extends TestCase
{
    private const GLOBAL_STATS = [
        'databaseSize' => 773808128,
        'usedDatabaseSize' => 368029696,
        'lastUpdate' => '2026-02-26T04:45:40.258654123Z',
    ];

    private const COMPACT_RECOMMENDATION = 'Fragmentation ratio is above 30%, compacting your indexes may improve performance. Run the meilisearch:compact command.';

    private const INDEXES_STATS = [
        'app_unconfigured' => [
            'numberOfDocuments' => 12,
            'indexSize' => 65536,
            'usedIndexSize' => 49152,
            'rawDocumentDbSize' => 3072,
            'avgDocumentSize' => 256,
            'isIndexing' => false,
            'numberOfEmbeddings' => 0,
            'numberOfEmbeddedDocuments' => 0,
            'fieldDistribution' => ['id' => 12],
        ],
        'app_tags' => [
            'numberOfDocuments' => 163999,
            'indexSize' => 536870912,
            'usedIndexSize' => 402653184,
            'rawDocumentDbSize' => 260046848,
            'avgDocumentSize' => 1536,
            'isIndexing' => true,
            'numberOfEmbeddings' => 326000,
            'numberOfEmbeddedDocuments' => 163000,
            'fieldDistribution' => ['id' => 163999, 'name' => 163999],
        ],
        'app_posts' => [
            'numberOfDocuments' => 49832,
            'indexSize' => 33554432,
            'usedIndexSize' => 25165824,
            'rawDocumentDbSize' => 16535633,
            'avgDocumentSize' => 308,
            'isIndexing' => false,
            'numberOfEmbeddings' => 0,
            'numberOfEmbeddedDocuments' => 0,
            'fieldDistribution' => ['id' => 49832, 'title' => 49832],
        ],
    ];

    private const CONFIGURED_INDICES = [
        ['name' => 'posts', 'prefixed_name' => 'app_posts', 'class' => Post::class],
        ['name' => 'comments', 'prefixed_name' => 'app_comments', 'class' => Comment::class],
        ['name' => 'tags', 'prefixed_name' => 'app_tags', 'class' => Tag::class],
        ['name' => 'tags', 'prefixed_name' => 'app_tags', 'class' => Link::class],
    ];

    /**
     * @var list<string>
     */
    private array $requests = [];

    /**
     * @param array{databaseSize: int, usedDatabaseSize: int, lastUpdate: string|null} $globalStats
     */
    #[DataProvider('provideGlobalStats')]
    public function testGlobalStatsTable(array $globalStats, string $expectedTable): void
    {
        $commandTester = new CommandTester($this->createCommand($globalStats));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        $this->assertContains('GET http://meilisearch.test/stats', $this->requests);
        $this->assertStringContainsString($expectedTable, $commandTester->getDisplay());
    }

    /**
     * @return iterable<string, array{globalStats: array{databaseSize: int, usedDatabaseSize: int, lastUpdate: string|null}, expectedTable: string}>
     */
    public static function provideGlobalStats(): iterable
    {
        yield 'fragmented database' => [
            'globalStats' => [
                'databaseSize' => 773808128,
                'usedDatabaseSize' => 368029696,
                'lastUpdate' => '2026-02-26T04:45:40.258654123Z',
            ],
            'expectedTable' => <<<'EOD'
+---------------+--------------------+ Global stats -------+------------------------------+
| Database size | Used database size | Fragmentation ratio | Last update                  |
+---------------+--------------------+---------------------+------------------------------+
| 737.96 MB     | 350.98 MB          | 52.44%              | 2026 Feb 26, 04:45:40.258654 |
+---------------+--------------------+---------------------+------------------------------+

EOD,
        ];

        yield 'empty database' => [
            'globalStats' => [
                'databaseSize' => 0,
                'usedDatabaseSize' => 0,
                'lastUpdate' => '2026-02-26T04:45:40.258654123Z',
            ],
            'expectedTable' => <<<'EOD'
+---------------+--------------------+ Global stats -------+------------------------------+
| Database size | Used database size | Fragmentation ratio | Last update                  |
+---------------+--------------------+---------------------+------------------------------+
| 0 B           | 0 B                | 0.00%               | 2026 Feb 26, 04:45:40.258654 |
+---------------+--------------------+---------------------+------------------------------+

EOD,
        ];

        yield 'never updated database' => [
            'globalStats' => [
                'databaseSize' => 773808128,
                'usedDatabaseSize' => 368029696,
                'lastUpdate' => null,
            ],
            'expectedTable' => <<<'EOD'
+---------------+------------- Global stats ---------------+-------------+
| Database size | Used database size | Fragmentation ratio | Last update |
+---------------+--------------------+---------------------+-------------+
| 737.96 MB     | 350.98 MB          | 52.44%              | -           |
+---------------+--------------------+---------------------+-------------+

EOD,
        ];

        yield 'kilobyte boundary' => [
            'globalStats' => [
                'databaseSize' => 1024,
                'usedDatabaseSize' => 1023,
                'lastUpdate' => null,
            ],
            'expectedTable' => <<<'EOD'
+---------------+------------- Global stats ---------------+-------------+
| Database size | Used database size | Fragmentation ratio | Last update |
+---------------+--------------------+---------------------+-------------+
| 1.00 KB       | 1023 B             | 0.10%               | -           |
+---------------+--------------------+---------------------+-------------+

EOD,
        ];

        yield 'terabyte database' => [
            'globalStats' => [
                'databaseSize' => 1649267441664,
                'usedDatabaseSize' => 5368709120,
                'lastUpdate' => null,
            ],
            'expectedTable' => <<<'EOD'
+---------------+------------- Global stats ---------------+-------------+
| Database size | Used database size | Fragmentation ratio | Last update |
+---------------+--------------------+---------------------+-------------+
| 1.50 TB       | 5.00 GB            | 99.67%              | -           |
+---------------+--------------------+---------------------+-------------+

EOD,
        ];
    }

    public function testIndexesStatsTableListsConfiguredIndexesInConfigurationOrder(): void
    {
        $commandTester = new CommandTester($this->createCommand(self::GLOBAL_STATS));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        $display = $commandTester->getDisplay();

        $this->assertStringContainsString(<<<'EOD'
+-----------+-----------------+------------------+------ Indexes stats ---------+------------------+--------------------------+
| Index     | No of documents | Document db size | Avg document size | Indexing | No of embeddings | No of embedded documents |
+-----------+-----------------+------------------+-------------------+----------+------------------+--------------------------+
| app_posts | 49,832          | 15.77 MB         | 308 B             | No       | 0                | 0                        |
| app_tags  | 163,999         | 248.00 MB        | 1.50 KB           | Yes      | 326,000          | 163,000                  |
+-----------+-----------------+------------------+-------------------+----------+------------------+--------------------------+

EOD, $display);
        $this->assertMatchesRegularExpression('/ Global stats .* Indexes stats /s', $display);
        $this->assertStringNotContainsString('app_unconfigured', $display);
        $this->assertStringNotContainsString('app_comments', $display);
    }

    public function testIndexesStatsTableIsRestrictedToGivenIndices(): void
    {
        $command = $this->createCommand(self::GLOBAL_STATS);

        $this->assertTrue($command->getDefinition()->hasOption('indices'));

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--indices' => 'tags']);

        $commandTester->assertCommandIsSuccessful();

        $display = $commandTester->getDisplay();

        $this->assertStringContainsString(<<<'EOD'
+----------+-----------------+------------------+------ Indexes stats ---------+------------------+--------------------------+
| Index    | No of documents | Document db size | Avg document size | Indexing | No of embeddings | No of embedded documents |
+----------+-----------------+------------------+-------------------+----------+------------------+--------------------------+
| app_tags | 163,999         | 248.00 MB        | 1.50 KB           | Yes      | 326,000          | 163,000                  |
+----------+-----------------+------------------+-------------------+----------+------------------+--------------------------+

EOD, $display);
        $this->assertStringNotContainsString('app_posts', $display);
    }

    /**
     * @param array{databaseSize: int, usedDatabaseSize: int, lastUpdate: string|null} $globalStats
     */
    #[DataProvider('provideFragmentedGlobalStats')]
    public function testCompactRecommendationIsPrintedAfterTheTablesWhenFragmentationIsAbove30Percent(array $globalStats): void
    {
        $commandTester = new CommandTester($this->createCommand($globalStats));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        $display = $commandTester->getDisplay();

        $this->assertStringContainsString("\n".self::COMPACT_RECOMMENDATION."\n", $display);
        $this->assertMatchesRegularExpression('/ Indexes stats .*^'.preg_quote(self::COMPACT_RECOMMENDATION, '/').'$/ms', $display);
    }

    /**
     * @return iterable<string, array{globalStats: array{databaseSize: int, usedDatabaseSize: int, lastUpdate: string|null}}>
     */
    public static function provideFragmentedGlobalStats(): iterable
    {
        yield 'fragmented database' => ['globalStats' => self::GLOBAL_STATS];

        yield 'just above threshold' => [
            'globalStats' => [
                'databaseSize' => 1000,
                'usedDatabaseSize' => 699,
                'lastUpdate' => null,
            ],
        ];
    }

    public function testCompactRecommendationIsStyledAsComment(): void
    {
        $commandTester = new CommandTester($this->createCommand(self::GLOBAL_STATS));
        $commandTester->execute([], ['decorated' => true]);

        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString("\e[33m".self::COMPACT_RECOMMENDATION."\e[39m", $commandTester->getDisplay());
    }

    /**
     * @param array{databaseSize: int, usedDatabaseSize: int, lastUpdate: string|null} $globalStats
     */
    #[DataProvider('provideUnfragmentedGlobalStats')]
    public function testCompactRecommendationIsNotPrintedWhenFragmentationIsAtMost30Percent(array $globalStats): void
    {
        $commandTester = new CommandTester($this->createCommand($globalStats));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        $this->assertStringNotContainsString('meilisearch:compact', $commandTester->getDisplay());
    }

    /**
     * @return iterable<string, array{globalStats: array{databaseSize: int, usedDatabaseSize: int, lastUpdate: string|null}}>
     */
    public static function provideUnfragmentedGlobalStats(): iterable
    {
        yield 'exactly at threshold' => [
            'globalStats' => [
                'databaseSize' => 1000,
                'usedDatabaseSize' => 700,
                'lastUpdate' => null,
            ],
        ];

        yield 'just below threshold' => [
            'globalStats' => [
                'databaseSize' => 1000,
                'usedDatabaseSize' => 701,
                'lastUpdate' => null,
            ],
        ];

        yield 'empty database' => [
            'globalStats' => [
                'databaseSize' => 0,
                'usedDatabaseSize' => 0,
                'lastUpdate' => null,
            ],
        ];
    }

    /**
     * @param array{databaseSize: int, usedDatabaseSize: int, lastUpdate: string|null} $globalStats
     */
    private function createCommand(array $globalStats): MeilisearchStatsCommand
    {
        $statsResponseBody = json_encode($globalStats + ['indexes' => self::INDEXES_STATS], \JSON_THROW_ON_ERROR);

        $httpClient = new MockHttpClient(function (string $method, string $url) use ($statsResponseBody): MockResponse {
            $this->requests[] = $method.' '.$url;

            return new MockResponse($statsResponseBody, ['response_headers' => ['content-type' => 'application/json']]);
        });

        $searchManager = $this->createStub(SearchManagerInterface::class);
        $searchManager->method('getConfiguration')->willReturn(new Collection([
            'prefix' => 'app_',
            'indices' => self::CONFIGURED_INDICES,
        ]));

        $client = new Client('http://meilisearch.test', 'masterKey', new Psr18Client($httpClient));

        return new MeilisearchStatsCommand($searchManager, new Engine($client));
    }
}
