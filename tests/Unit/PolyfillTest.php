<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit;

use Meilisearch\Contracts\Task;
use Meilisearch\Contracts\TaskType;
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
}
