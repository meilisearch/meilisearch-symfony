<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit;

use Meilisearch\Contracts\Task;
use Meilisearch\Contracts\TaskDetails\IndexCompactionDetails;
use Meilisearch\Contracts\TaskType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the polyfill on meilisearch-php 1.x and the library's own classes on 2.0, so both
 * supported versions have to map a task payload to the same type and details object.
 */
final class PolyfillTest extends TestCase
{
    public function testKnownTaskTypeWithEmptyDetailsHasNoDetailsObject(): void
    {
        $task = Task::fromArray([
            'uid' => 42,
            'indexUid' => 'posts',
            'status' => 'succeeded',
            'type' => 'dumpCreation',
            'enqueuedAt' => '2026-01-01T00:00:00Z',
            'details' => [],
        ]);

        self::assertSame(TaskType::DumpCreation, $task->getType());
        self::assertNull($task->getDetails());
    }

    public function testUnknownTaskTypeWithEmptyDetailsKeepsItsDetailsObject(): void
    {
        $task = Task::fromArray([
            'uid' => 42,
            'status' => 'succeeded',
            'type' => 'aTypeOfAFutureMeilisearchVersion',
            'enqueuedAt' => '2026-01-01T00:00:00Z',
            'details' => [],
        ]);

        self::assertSame(TaskType::Unknown, $task->getType());
        self::assertNotNull($task->getDetails());
    }

    public function testIndexCompactionTaskCarriesItsDetails(): void
    {
        $task = Task::fromArray([
            'uid' => 42,
            'indexUid' => 'posts',
            'status' => 'succeeded',
            'type' => 'indexCompaction',
            'enqueuedAt' => '2026-01-01T00:00:00Z',
            'details' => [
                'preCompactionSize' => '1.1 GiB',
                'postCompactionSize' => '900.0 MiB',
            ],
        ]);

        self::assertSame(TaskType::IndexCompaction, $task->getType());

        $details = $task->getDetails();

        self::assertInstanceOf(IndexCompactionDetails::class, $details);
        self::assertSame('1.1 GiB', $details->preCompactionSize);
        self::assertSame('900.0 MiB', $details->postCompactionSize);
    }

    #[DataProvider('dataTestTaskTypesWithoutDetails')]
    public function testTaskTypeWithoutDetails(string $rawType, TaskType $expectedType): void
    {
        $task = Task::fromArray([
            'uid' => 42,
            'status' => 'succeeded',
            'type' => $rawType,
            'enqueuedAt' => '2026-01-01T00:00:00Z',
        ]);

        self::assertSame($expectedType, $task->getType());
        self::assertNull($task->getDetails());
    }

    /**
     * @return iterable<string, array{string, TaskType}>
     */
    public static function dataTestTaskTypesWithoutDetails(): iterable
    {
        yield 'snapshotCreation' => ['snapshotCreation', TaskType::SnapshotCreation];
        yield 'networkTopologyChange' => ['networkTopologyChange', TaskType::NetworkTopologyChange];
        yield 'dsrUpdate' => ['dsrUpdate', TaskType::DsrUpdate];
        yield 'dsrClear' => ['dsrClear', TaskType::DsrClear];
    }
}
