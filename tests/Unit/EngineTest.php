<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit;

use Meilisearch\Bundle\Engine;
use Meilisearch\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

final class EngineTest extends TestCase
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $sentBodies = [];

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

    private function createEngine(): Engine
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturnCallback(function (RequestInterface $request): Response {
            $this->sentBodies[] = json_decode((string) $request->getBody(), true, 512, \JSON_THROW_ON_ERROR);

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
