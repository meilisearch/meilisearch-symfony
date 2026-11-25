<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit\Services;

use Meilisearch\Bundle\DataProvider\DataProviderInterface;
use Meilisearch\Bundle\DataProvider\DataProviderRegistryInterface;
use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\Services\MeilisearchManager;
use Meilisearch\Bundle\Tests\Entity\Post;
use Meilisearch\Client;
use Meilisearch\Contracts\Task;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class MeilisearchManagerTest extends TestCase
{
    /**
     * @var list<non-empty-string>
     */
    private array $sentRequests = [];

    private int $nextTaskUid = 0;

    public function testRemoveKeepsTheDeletionTasksOfEveryChunk(): void
    {
        $manager = $this->createManager(1);

        $tasks = $manager->remove([new Post(), new Post(), new Post()]);

        self::assertSame(['sf_posts'], array_keys($tasks));
        self::assertContainsOnlyInstancesOf(Task::class, $tasks['sf_posts']);
        self::assertSame([1, 2, 3], array_map(static fn (Task $task): int => $task->getTaskUid(), $tasks['sf_posts']));
        self::assertCount(3, $this->sentRequests);
    }

    public function testRemoveWithoutChunkingReturnsTheTasksOfTheSingleChunk(): void
    {
        $manager = $this->createManager(100);

        $tasks = $manager->remove([new Post()]);

        self::assertSame(['sf_posts'], array_keys($tasks));
        self::assertContainsOnlyInstancesOf(Task::class, $tasks['sf_posts']);
        self::assertCount(1, $tasks['sf_posts']);
        self::assertCount(1, $this->sentRequests);
    }

    /**
     * @param positive-int $batchSize
     */
    private function createManager(int $batchSize): MeilisearchManager
    {
        $nextIdentifier = 0;

        $dataProvider = $this->createStub(DataProviderInterface::class);
        $dataProvider->method('getIdentifierValues')->willReturnCallback(
            static function () use (&$nextIdentifier): array {
                return ['id' => ++$nextIdentifier];
            }
        );
        $dataProvider->method('normalizeIdentifiers')->willReturnCallback(
            static fn (array $identifiers): int => $identifiers['id']
        );

        $registry = $this->createStub(DataProviderRegistryInterface::class);
        $registry->method('getDataProvider')->willReturn($dataProvider);

        return new MeilisearchManager(
            $this->createStub(NormalizerInterface::class),
            $this->createEngine(),
            $this->createStub(PropertyAccessorInterface::class),
            $registry,
            [
                'prefix' => 'sf_',
                'batchSize' => $batchSize,
                'indices' => [
                    [
                        'name' => 'posts',
                        'class' => Post::class,
                        'enable_serializer_groups' => false,
                        'serializer_groups' => [],
                        'primary_key' => 'id',
                    ],
                ],
            ],
        );
    }

    private function createEngine(): Engine
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturnCallback(function (RequestInterface $request): Response {
            $this->sentRequests[] = $request->getMethod().' '.$request->getUri()->getPath();

            return new Response(202, ['Content-Type' => 'application/json'], json_encode([
                'taskUid' => ++$this->nextTaskUid,
                'indexUid' => 'sf_posts',
                'status' => 'enqueued',
                'type' => 'documentDeletion',
                'enqueuedAt' => '2026-01-01T00:00:00Z',
            ], \JSON_THROW_ON_ERROR));
        });

        $factory = new Psr17Factory();

        return new Engine(new Client('http://meilisearch.test', 'masterKey', $httpClient, $factory, [], $factory));
    }
}
