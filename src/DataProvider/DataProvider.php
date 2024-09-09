<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\DataProvider;

interface DataProvider
{
    /**
     * Returns every objects that need to be indexed.
     *
     * @param int $limit
     * @param int $offset
     *
     * @return array
     */
    public function getAll(int $limit = 100, int $offset = 0): array;
}
