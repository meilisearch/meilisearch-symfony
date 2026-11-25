<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit\Services;

use Meilisearch\Bundle\Contracts\SearchQuery;
use Meilisearch\Bundle\DataProvider\DataProviderInterface;
use Meilisearch\Bundle\DataProvider\DataProviderRegistryInterface;
use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\Model\SearchResults;
use Meilisearch\Bundle\Services\MeilisearchManager;
use Meilisearch\Bundle\Tests\Entity\Movie;
use Meilisearch\Bundle\Tests\Entity\Post;
use Meilisearch\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class MeilisearchManagerMultiSearchTest extends TestCase
{
    /**
     * @var list<non-empty-string>
     */
    private array $sentRequests = [];

    /**
     * @var list<array<mixed>>
     */
    private array $sentBodies = [];

    public function testOnlyTheIndicesAskedForAreQueried(): void
    {
        $this->createManager()->multiSearch([new SearchQuery(Post::class, 'first')]);

        self::assertSame(['POST /multi-search'], $this->sentRequests);
        self::assertSame([
            'queries' => [
                ['indexUid' => 'app_posts', 'q' => 'first', 'limit' => 20],
            ],
        ], $this->sentBodies[0]);
    }

    public function testEachQueryIsAnsweredInOrderAndHydratedWithItsOwnClass(): void
    {
        $results = $this->createManager()->multiSearch([
            new SearchQuery(Post::class, 'first'),
            new SearchQuery(Movie::class, 'second', ['limit' => 5]),
        ]);

        self::assertSame([
            'queries' => [
                ['indexUid' => 'app_posts', 'q' => 'first', 'limit' => 20],
                ['indexUid' => 'app_movies', 'q' => 'second', 'limit' => 5],
            ],
        ], $this->sentBodies[0]);

        self::assertCount(2, $results);
        self::assertContainsOnlyInstancesOf(SearchResults::class, $results);

        self::assertContainsOnlyInstancesOf(Post::class, $results[0]->getHits());
        self::assertContainsOnlyInstancesOf(Movie::class, $results[1]->getHits());
        self::assertSame('first', $results[0]->getQuery());
        self::assertSame('second', $results[1]->getQuery());
    }

    public function testSearchParametersAreSentUnchanged(): void
    {
        // Same contract as a single search: a null filter is accepted, and a parameter the
        // typed client query knows nothing about still reaches the engine.
        $this->createManager()->multiSearch([
            new SearchQuery(Post::class, 'first', [
                'filter' => null,
                'useNetwork' => true,
                'personalize' => ['userContext' => 'likes jazz'],
            ]),
        ]);

        self::assertSame([
            'queries' => [
                [
                    'indexUid' => 'app_posts',
                    'q' => 'first',
                    'filter' => null,
                    'useNetwork' => true,
                    'personalize' => ['userContext' => 'likes jazz'],
                    'limit' => 20,
                ],
            ],
        ], $this->sentBodies[0]);
    }

    public function testTheRawResultKeepsTheIndexItCameFrom(): void
    {
        $results = $this->createManager()->multiSearch([new SearchQuery(Post::class, 'first')]);

        self::assertSame('app_posts', $results[0]->getRaw()['indexUid'] ?? null);
    }

    public function testNoQueriesSendsNoRequest(): void
    {
        self::assertSame([], $this->createManager()->multiSearch([]));
        self::assertSame([], $this->sentRequests);
    }

    private function createManager(): MeilisearchManager
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturnCallback(function (RequestInterface $request): Response {
            $this->sentRequests[] = $request->getMethod().' '.$request->getUri()->getPath();
            $this->sentBodies[] = json_decode((string) $request->getBody(), true, 512, \JSON_THROW_ON_ERROR);

            // One result per query, in the order asked for, as the engine answers.
            $results = [];
            foreach ($this->sentBodies[array_key_last($this->sentBodies)]['queries'] as $query) {
                $results[] = [
                    'indexUid' => $query['indexUid'],
                    'hits' => [['id' => 'app_posts' === $query['indexUid'] ? 1 : 2]],
                    'query' => $query['q'],
                    'processingTimeMs' => 1,
                    'limit' => $query['limit'],
                    'offset' => 0,
                    'estimatedTotalHits' => 1,
                ];
            }

            return new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'results' => $results,
            ], \JSON_THROW_ON_ERROR));
        });

        $registry = $this->createStub(DataProviderRegistryInterface::class);
        $registry->method('getDataProvider')->willReturnCallback(
            fn (string $indexName): DataProviderInterface => $this->createProviderFor(
                'posts' === $indexName ? new Post('A post') : new Movie('A movie')
            )
        );

        $factory = new Psr17Factory();

        return new MeilisearchManager(
            $this->createStub(NormalizerInterface::class),
            new Engine(new Client('http://meilisearch.test', 'masterKey', $httpClient, $factory, [], $factory)),
            $this->createStub(PropertyAccessorInterface::class),
            $registry,
            [
                'prefix' => 'app_',
                'batchSize' => 100,
                'nbResults' => 20,
                'indices' => [
                    $this->index('posts', Post::class),
                    $this->index('movies', Movie::class),
                ],
            ],
        );
    }

    private function createProviderFor(object $entity): DataProviderInterface
    {
        $provider = $this->createStub(DataProviderInterface::class);
        $provider->method('loadByIdentifiers')->willReturn([$entity]);
        $provider->method('getIdentifierValues')->willReturn(['id' => $entity instanceof Post ? 1 : 2]);
        $provider->method('normalizeIdentifiers')->willReturnCallback(
            static fn (array $identifiers): int => $identifiers['id']
        );

        return $provider;
    }

    /**
     * @param class-string $class
     *
     * @return array<string, mixed>
     */
    private function index(string $name, string $class): array
    {
        return [
            'name' => $name,
            'prefixed_name' => 'app_'.$name,
            'class' => $class,
            'enable_serializer_groups' => false,
            'serializer_groups' => [],
            'primary_key' => 'id',
        ];
    }
}
