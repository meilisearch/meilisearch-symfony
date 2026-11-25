<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit;

use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\SearchableObject;
use Meilisearch\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class EngineTest extends TestCase
{
    /**
     * @var list<array<mixed>>
     */
    private array $sentBodies = [];

    /**
     * @var list<non-empty-string>
     */
    private array $sentRequests = [];

    /**
     * @param array<mixed> $searchParams
     */
    #[DataProvider('dataTestSearchParametersAreSentUnchanged')]
    public function testSearchSendsParametersUnchanged(array $searchParams): void
    {
        $this->createEngine()->search('query', 'posts', $searchParams);

        $this->assertEquals([['q' => 'query'] + $searchParams], $this->sentBodies);
    }

    /**
     * @param array<mixed> $searchParams
     */
    #[DataProvider('dataTestSearchParametersAreSentUnchanged')]
    public function testCountSendsParametersUnchanged(array $searchParams): void
    {
        $this->assertSame(1, $this->createEngine()->count('query', 'posts', $searchParams));

        $this->assertEquals([['q' => 'query'] + $searchParams], $this->sentBodies);
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function dataTestSearchParametersAreSentUnchanged(): iterable
    {
        yield 'no parameters' => [[]];
        yield 'null filter' => [['filter' => null]];
        yield 'string filter' => [['filter' => 'id = 1']];
        yield 'parameters unknown to the typed client query' => [['personalize' => ['userContext' => 'likes jazz'], 'useNetwork' => true]];
        yield 'parameter of a future Meilisearch version' => [['futureParameter' => ['enabled' => true]]];
        yield 'value the typed client query would reject' => [['limit' => '10']];
        yield 'nested option unknown to the typed client query' => [['hybrid' => ['embedder' => 'default', 'futureOption' => 1]]];
    }

    public function testSearchParametersTakePrecedenceOverQueryArgument(): void
    {
        $this->createEngine()->search('query', 'posts', ['q' => 'from parameters']);

        $this->assertEquals([['q' => 'from parameters']], $this->sentBodies);
    }

    public function testRemoveDeletesTheDocumentsOfAnIndexInOneRequest(): void
    {
        $normalizer = $this->createStub(NormalizerInterface::class);

        $tasks = $this->createEngine()->remove([
            new SearchableObject('posts', 'objectID', new \stdClass(), 1, $normalizer),
            new SearchableObject('posts', 'objectID', new \stdClass(), 2, $normalizer),
            new SearchableObject('tags', 'objectID', new \stdClass(), 3, $normalizer),
        ]);

        $this->assertSame([
            'POST /indexes/posts/documents/delete-batch',
            'POST /indexes/tags/documents/delete-batch',
        ], $this->sentRequests);
        $this->assertSame([[1, 2], [3]], $this->sentBodies);
        $this->assertCount(1, $tasks['posts']);
        $this->assertCount(1, $tasks['tags']);
    }

    private function createEngine(): Engine
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturnCallback(function (RequestInterface $request): Response {
            $path = $request->getUri()->getPath();
            $body = (string) $request->getBody();

            $this->sentRequests[] = $request->getMethod().' '.$path;

            if ('' !== $body) {
                $this->sentBodies[] = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
            }

            if (str_contains($path, '/documents')) {
                return new Response(202, ['Content-Type' => 'application/json'], json_encode([
                    'taskUid' => 1,
                    'status' => 'enqueued',
                    'type' => 'documentDeletion',
                    'enqueuedAt' => '2026-01-01T00:00:00Z',
                ], \JSON_THROW_ON_ERROR));
            }

            return new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'hits' => [['id' => 1]],
                'query' => 'query',
                'processingTimeMs' => 1,
                'limit' => 20,
                'offset' => 0,
                'estimatedTotalHits' => 1,
            ], \JSON_THROW_ON_ERROR));
        });

        $factory = new Psr17Factory();

        return new Engine(new Client('http://meilisearch.test', 'masterKey', $httpClient, $factory, [], $factory));
    }
}
