<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit;

use Meilisearch\Contracts\TaskType;
use Meilisearch\Endpoints\Index;
use PHPUnit\Framework\TestCase;

final class PolyfillGuardTest extends TestCase
{
    public function testThePolyfillOnlyDefinesWhatTheInstalledClientIsMissing(): void
    {
        $declaredIn = (string) (new \ReflectionEnum(TaskType::class))->getFileName();

        // Engine uses this class as its 2.0 probe; the polyfill deliberately leaves it alone.
        if (!class_exists(Index::class)) {
            self::assertStringEndsWith('src/polyfill.php', $declaredIn, 'meilisearch-php 1.x needs the polyfill');

            return;
        }

        self::assertStringEndsWith(
            'meilisearch-php/src/Contracts/TaskType.php',
            $declaredIn,
            'meilisearch-php 2.0 ships this class, so the polyfill must not shadow it',
        );
    }
}
