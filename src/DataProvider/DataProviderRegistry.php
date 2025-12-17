<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\DataProvider;

use Meilisearch\Bundle\Exception\DataProviderNotFoundException;
use Psr\Container\ContainerInterface;

final class DataProviderRegistry implements DataProviderRegistryInterface
{
    /**
     * @param array<non-empty-string, array<class-string, non-empty-string>> $dataProvidersMap
     */
    public function __construct(
        private readonly ContainerInterface $dataProviders,
        private readonly array $dataProvidersMap,
    ) {
    }

    public function getDataProvider(string $indexName, string $className): DataProviderInterface
    {
        if (isset($this->dataProvidersMap[$indexName][$className])) {
            return $this->dataProviders->get($this->dataProvidersMap[$indexName][$className]);
        }

        if (isset($this->dataProvidersMap[$indexName])) {
            foreach ($this->dataProvidersMap[$indexName] as $registeredClass => $locatorKey) {
                if (is_a($className, $registeredClass, true)) {
                    return $this->dataProviders->get($locatorKey);
                }
            }
        }

        throw new DataProviderNotFoundException($indexName, $className);
    }
}
