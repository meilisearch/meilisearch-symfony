<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\DataProvider;

use Meilisearch\Bundle\Exception\DataProviderNotFoundException;

interface DataProviderRegistryInterface
{
    /**
     * @template T of object
     *
     * @param non-empty-string $indexName
     * @param class-string<T>  $className
     *
     * @return DataProviderInterface<T>
     *
     * @throws DataProviderNotFoundException
     */
    public function getDataProvider(string $indexName, string $className): DataProviderInterface;
}
