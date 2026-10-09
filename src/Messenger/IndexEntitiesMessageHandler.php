<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Messenger;

use Meilisearch\Bundle\DataProvider\DataProviderRegistryInterface;
use Meilisearch\Bundle\SearchManagerInterface;

/**
 * Reloads the live objects referenced by an {@see IndexEntitiesMessage} and hands
 * them to the search manager for (re)indexing.
 *
 * Objects are loaded through the data provider registered for the index, so this
 * works for any source (Doctrine ORM or a custom data provider) and any identifier
 * shape the provider supports. Identifiers that no longer resolve to an object are
 * silently skipped (e.g. deleted again before the message was consumed).
 */
final class IndexEntitiesMessageHandler
{
    public function __construct(
        private readonly SearchManagerInterface $searchManager,
        private readonly DataProviderRegistryInterface $dataProviderRegistry,
    ) {
    }

    public function __invoke(IndexEntitiesMessage $message): void
    {
        foreach ($message->getIdentifiersByIndex() as $indexName => $identifiersByClass) {
            foreach ($identifiersByClass as $class => $identifiers) {
                if ([] === $identifiers || !$this->searchManager->isSearchable($class)) {
                    continue;
                }

                $objects = $this->dataProviderRegistry->getDataProvider($indexName, $class)->loadByIdentifiers($identifiers);
                $objects = \is_array($objects) ? array_values($objects) : iterator_to_array($objects, false);

                if ([] === $objects) {
                    continue;
                }

                $this->searchManager->index($objects);
            }
        }
    }
}
