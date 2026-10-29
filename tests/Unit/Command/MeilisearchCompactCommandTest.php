<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit\Command;

use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\Command\MeilisearchCompactCommand;
use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\SearchManagerInterface;
use Meilisearch\Bundle\Tests\Entity\Comment;
use Meilisearch\Bundle\Tests\Entity\Page;
use Meilisearch\Bundle\Tests\Entity\Post;
use Meilisearch\Client;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MeilisearchCompactCommandTest extends TestCase
{
    private const CONFIGURED_INDICES = [
        ['name' => 'comments', 'prefixed_name' => 'app_comments', 'class' => Comment::class],
        ['name' => 'pages', 'prefixed_name' => 'app_pages', 'class' => Page::class],
        ['name' => 'posts', 'prefixed_name' => 'app_posts', 'class' => Post::class],
    ];

    public function testFailedCompactionIsReportedAndTheNextIndexIsStillCompacted(): void
    {
        $applicationTester = $this->createApplicationTester();
        $applicationTester->run(['command' => 'meilisearch:compact', '--indices' => 'comments,posts']);

        $this->assertSame(<<<'EOD'
Index app_comments couldn't be compacted: Index `app_comments` not found.
Compacted app_posts
Done!

EOD, $applicationTester->getDisplay());
        $applicationTester->assertCommandIsSuccessful();
    }

    public function testRejectedCompactionRequestIsReportedAndTheNextIndexIsStillCompacted(): void
    {
        $applicationTester = $this->createApplicationTester();
        $applicationTester->run(['command' => 'meilisearch:compact', '--indices' => 'pages,posts']);

        $this->assertSame(<<<'EOD'
Cannot compact app_pages: The API key cannot acces the index `app_pages`, authorized indexes are ["app_posts"].
Compacted app_posts
Done!

EOD, $applicationTester->getDisplay());
        $applicationTester->assertCommandIsSuccessful();
    }

    private function createApplicationTester(): ApplicationTester
    {
        $httpClient = new MockHttpClient(fn (string $method, string $url): MockResponse => match ($method.' '.$url) {
            'POST http://meilisearch.test/indexes/app_comments/compact' => self::jsonResponse(202, self::enqueuedCompaction(1, 'app_comments')),
            'GET http://meilisearch.test/tasks/1' => self::jsonResponse(200, self::finishedCompaction(1, 'app_comments', 'failed', [
                'message' => 'Index `app_comments` not found.',
                'code' => 'index_not_found',
                'type' => 'invalid_request',
                'link' => 'https://docs.meilisearch.com/errors#index_not_found',
            ])),
            'POST http://meilisearch.test/indexes/app_pages/compact' => self::jsonResponse(403, [
                'message' => 'The API key cannot acces the index `app_pages`, authorized indexes are ["app_posts"].',
                'code' => 'invalid_api_key',
                'type' => 'auth',
                'link' => 'https://docs.meilisearch.com/errors#invalid_api_key',
            ]),
            'POST http://meilisearch.test/indexes/app_posts/compact' => self::jsonResponse(202, self::enqueuedCompaction(2, 'app_posts')),
            'GET http://meilisearch.test/tasks/2' => self::jsonResponse(200, self::finishedCompaction(2, 'app_posts', 'succeeded', null)),
            default => throw new \LogicException(\sprintf('Unexpected request "%s %s".', $method, $url)),
        });

        $searchManager = $this->createStub(SearchManagerInterface::class);
        $searchManager->method('getConfiguration')->willReturn(new Collection([
            'prefix' => 'app_',
            'indices' => self::CONFIGURED_INDICES,
        ]));

        $client = new Client('http://meilisearch.test', 'masterKey', new Psr18Client($httpClient));
        $command = new MeilisearchCompactCommand($searchManager, new Engine($client));

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCommandLoader(new FactoryCommandLoader(['meilisearch:compact' => fn () => $command]));

        return new ApplicationTester($application);
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function jsonResponse(int $statusCode, array $body): MockResponse
    {
        return new MockResponse(json_encode($body, \JSON_THROW_ON_ERROR), [
            'http_code' => $statusCode,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function enqueuedCompaction(int $taskUid, string $indexUid): array
    {
        return [
            'taskUid' => $taskUid,
            'indexUid' => $indexUid,
            'status' => 'enqueued',
            'type' => 'indexCompaction',
            'enqueuedAt' => '2026-10-29T19:01:29.520465955Z',
        ];
    }

    /**
     * @param array{message: string, code: string, type: string, link: string}|null $error
     *
     * @return array<string, mixed>
     */
    private static function finishedCompaction(int $taskUid, string $indexUid, string $status, ?array $error): array
    {
        return [
            'uid' => $taskUid,
            'batchUid' => $taskUid,
            'indexUid' => $indexUid,
            'status' => $status,
            'type' => 'indexCompaction',
            'canceledBy' => null,
            'details' => [],
            'error' => $error,
            'duration' => 'PT0.000455668S',
            'enqueuedAt' => '2026-10-29T19:01:29.520465955Z',
            'startedAt' => '2026-10-29T19:01:29.529280744Z',
            'finishedAt' => '2026-10-29T19:01:29.529736412Z',
        ];
    }
}
