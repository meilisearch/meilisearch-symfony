<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Entity;

final class Actor
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {
    }
}
