<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Messenger;

use Meilisearch\Bundle\DataProvider\DataProviderRegistryInterface;
use Meilisearch\Bundle\SearchManagerInterface;

/**
 * Reloads the live objects referenced by an {@see IndexSearchablesMessage} and hands
 * them to the search manager for (re)indexing.
 *
 * Objects are loaded through the data provider registered for the index, so this
 * works for any source (Doctrine ORM or a custom data provider) and any identifier
 * shape the provider supports. Identifiers that no longer resolve to an object are
 * silently skipped (e.g. deleted again before the message was consumed).
 *
 * Identifiers are loaded in chunks of the configured batch size, which keeps each
 * query within the bind-parameter limits of the database.
 */
final class IndexSearchablesMessageHandler
{
    public function __construct(
        private readonly SearchManagerInterface $searchManager,
        private readonly DataProviderRegistryInterface $dataProviderRegistry,
    ) {
    }

    public function __invoke(IndexSearchablesMessage $message): void
    {
        $batchSize = $this->searchManager->getConfiguration()->get('batchSize');

        foreach ($message->getIdentifiersByIndex() as $indexName => $identifiersByClass) {
            foreach ($identifiersByClass as $class => $identifiers) {
                if ([] === $identifiers) {
                    continue;
                }

                $dataProvider = $this->dataProviderRegistry->getDataProvider($indexName, $class);

                foreach (array_chunk($identifiers, $batchSize) as $chunk) {
                    $objects = $dataProvider->loadByIdentifiers($chunk);
                    $objects = \is_array($objects) ? array_values($objects) : iterator_to_array($objects, false);

                    if ([] === $objects) {
                        continue;
                    }

                    $this->searchManager->index($objects);
                }
            }
        }
    }
}
