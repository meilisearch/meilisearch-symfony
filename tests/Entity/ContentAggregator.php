<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Entity;

use Meilisearch\Bundle\Entity\Aggregator;

class ContentAggregator extends Aggregator
{
    public function getIsVisible(): bool
    {
        return true;
    }

    public static function getEntities(): array
    {
        return [
            Post::class,
            Tag::class,
        ];
    }
}
