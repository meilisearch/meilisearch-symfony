<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Test\Entity\ObjectId;

class DummyObjectId
{
    private int $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function __toString()
    {
        return (string) $this->id;
    }
}
