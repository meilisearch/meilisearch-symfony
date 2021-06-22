<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Test\Entity;

use Doctrine\ORM\Mapping as ORM;
use MeiliSearch\Bundle\Entity\Aggregator;

/**
 * @ORM\Entity
 */
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
