<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Entity\ObjectId;

final class DummyObjectId implements \Stringable
{
    private int $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function toInt(): int
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }
}
