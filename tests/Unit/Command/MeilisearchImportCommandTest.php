<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit\Command;

use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\Command\MeilisearchImportCommand;
use Meilisearch\Bundle\DataProvider\DataProviderInterface;
use Meilisearch\Bundle\DataProvider\DataProviderRegistryInterface;
use Meilisearch\Bundle\SearchManagerInterface;
use Meilisearch\Bundle\Services\SettingsUpdater;
use Meilisearch\Bundle\Tests\Entity\Post;
use Meilisearch\Client;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MeilisearchImportCommandTest extends TestCase
{
    /**
     * @var list<non-empty-string>
     */
    private array $sentRequests = [];

    public function testAFailedSettingsUpdateStopsTheImport(): void
    {
        $applicationTester = $this->createApplicationTester();
        $exitCode = $applicationTester->run(['command' => 'meilisearch:import', '--indices' => 'posts']);

        self::assertStringContainsString(
            'Settings of the app_posts index couldn\'t be updated: Index `app_posts` not found.',
            $applicationTester->getDisplay(),
        );
        self::assertSame(1, $exitCode);

        // The import stops at the failed index: no documents are sent and no index is swapped.
        self::assertSame([
            'PUT /indexes/app_posts/settings/stop-words',
            'GET /tasks/1',
        ], $this->sentRequests);
    }

    public function testAFailedSettingsUpdateStopsTheImportBeforeAnyIndexIsSwapped(): void
    {
        $applicationTester = $this->createApplicationTester();
        $exitCode = $applicationTester->run(['command' => 'meilisearch:import', '--indices' => 'posts', '--swap-indices' => true]);

        self::assertStringContainsString(
            'Settings of the app_posts index couldn\'t be updated: Index `_tmp_app_posts` not found.',
            $applicationTester->getDisplay(),
        );
        self::assertSame(1, $exitCode);

        // Without --swap-indices nothing is swapped anyway; with it, reaching the end of the
        // command would swap the temporary indices into place.
        self::assertSame([
            'PUT /indexes/_tmp_app_posts/settings/stop-words',
            'GET /tasks/2',
        ], $this->sentRequests);
    }

    private function createApplicationTester(): ApplicationTester
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $path = parse_url($url, \PHP_URL_PATH);
            $this->sentRequests[] = $method.' '.$path;

            return match ($method.' '.$path) {
                'PUT /indexes/app_posts/settings/stop-words' => self::jsonResponse(202, [
                    'taskUid' => 1,
                    'indexUid' => 'app_posts',
                    'status' => 'enqueued',
                    'type' => 'settingsUpdate',
                    'enqueuedAt' => '2026-01-01T00:00:00Z',
                ]),
                'GET /tasks/1' => self::jsonResponse(200, [
                    'uid' => 1,
                    'indexUid' => 'app_posts',
                    'status' => 'failed',
                    'type' => 'settingsUpdate',
                    'details' => [],
                    'error' => [
                        'message' => 'Index `app_posts` not found.',
                        'code' => 'index_not_found',
                        'type' => 'invalid_request',
                        'link' => 'https://docs.meilisearch.com/errors#index_not_found',
                    ],
                    'duration' => 'PT0.000455668S',
                    'enqueuedAt' => '2026-01-01T00:00:00Z',
                    'startedAt' => '2026-01-01T00:00:01Z',
                    'finishedAt' => '2026-01-01T00:00:02Z',
                ]),
                'PUT /indexes/_tmp_app_posts/settings/stop-words' => self::jsonResponse(202, [
                    'taskUid' => 2,
                    'indexUid' => '_tmp_app_posts',
                    'status' => 'enqueued',
                    'type' => 'settingsUpdate',
                    'enqueuedAt' => '2026-01-01T00:00:00Z',
                ]),
                'GET /tasks/2' => self::jsonResponse(200, [
                    'uid' => 2,
                    'indexUid' => '_tmp_app_posts',
                    'status' => 'failed',
                    'type' => 'settingsUpdate',
                    'details' => [],
                    'error' => [
                        'message' => 'Index `_tmp_app_posts` not found.',
                        'code' => 'index_not_found',
                        'type' => 'invalid_request',
                        'link' => 'https://docs.meilisearch.com/errors#index_not_found',
                    ],
                    'duration' => 'PT0.000455668S',
                    'enqueuedAt' => '2026-01-01T00:00:00Z',
                    'startedAt' => '2026-01-01T00:00:01Z',
                    'finishedAt' => '2026-01-01T00:00:02Z',
                ]),
                default => throw new \LogicException(\sprintf('The import did not stop: it sent "%s %s".', $method, $path)),
            };
        });

        $searchManager = $this->createStub(SearchManagerInterface::class);
        $searchManager->method('getConfiguration')->willReturn(new Collection([
            'prefix' => 'app_',
            'batchSize' => 100,
            'indices' => [
                [
                    'name' => 'posts',
                    'prefixed_name' => 'app_posts',
                    'class' => Post::class,
                    'enable_serializer_groups' => false,
                    'serializer_groups' => [],
                    'primary_key' => 'id',
                    'settings' => ['stopWords' => ['the']],
                ],
            ],
        ]));
        $searchManager->method('isSearchable')->willReturn(true);

        $dataProvider = $this->createStub(DataProviderInterface::class);
        $dataProvider->method('provide')->willThrowException(
            new \LogicException('The import did not stop: it asked the data provider for entities.')
        );

        $registry = $this->createStub(DataProviderRegistryInterface::class);
        $registry->method('getDataProvider')->willReturn($dataProvider);

        $client = new Client('http://meilisearch.test', 'masterKey', new Psr18Client($httpClient));
        $eventDispatcher = new EventDispatcher();

        $command = new MeilisearchImportCommand(
            $searchManager,
            $client,
            new SettingsUpdater($searchManager, $client, $eventDispatcher),
            $eventDispatcher,
            $registry,
        );

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCommandLoader(new FactoryCommandLoader(['meilisearch:import' => fn () => $command]));

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
}
