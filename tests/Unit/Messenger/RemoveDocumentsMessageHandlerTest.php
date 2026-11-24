<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit\Messenger;

use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\Messenger\RemoveDocumentsMessage;
use Meilisearch\Bundle\Messenger\RemoveDocumentsMessageHandler;
use Meilisearch\Bundle\SearchManagerInterface;
use Meilisearch\Client;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RemoveDocumentsMessageHandlerTest extends TestCase
{
    public function testDocumentsAreRemovedInChunksFromTheIndexWithTheConfiguredPrefix(): void
    {
        $requests = [];

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, parse_url($url, \PHP_URL_PATH), json_decode($options['body'], true)];

            return new MockResponse(json_encode([
                'taskUid' => 1,
                'indexUid' => 'sf_posts',
                'status' => 'enqueued',
                'type' => 'documentDeletion',
                'enqueuedAt' => '2026-01-01T00:00:00Z',
            ]), ['http_code' => 202, 'response_headers' => ['content-type' => 'application/json']]);
        });

        $searchManager = $this->createStub(SearchManagerInterface::class);
        $searchManager->method('getConfiguration')->willReturn(new Collection(['prefix' => 'sf_', 'batchSize' => 2]));

        $engine = new Engine(new Client('http://meilisearch.test', 'masterKey', new Psr18Client($httpClient)));

        (new RemoveDocumentsMessageHandler($searchManager, $engine))(
            new RemoveDocumentsMessage(['posts' => [1, 2, 3], 'comments' => []])
        );

        $this->assertSame([
            ['POST', '/indexes/sf_posts/documents/delete-batch', [1, 2]],
            ['POST', '/indexes/sf_posts/documents/delete-batch', [3]],
        ], $requests);
    }
}
