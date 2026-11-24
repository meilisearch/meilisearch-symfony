<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Messenger;

use Meilisearch\Bundle\DataProvider\DataProviderInterface;
use Meilisearch\Bundle\DataProvider\DataProviderRegistryInterface;
use Meilisearch\Bundle\SearchManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Reloads the live objects referenced by an {@see IndexSearchablesMessage} and hands
 * them to the search manager for (re)indexing.
 *
 * Objects are loaded through the data provider registered for the index, so this
 * works for any source (Doctrine ORM or a custom data provider) and any identifier
 * shape the provider supports. Identifiers that no longer resolve to an object are
 * skipped and logged as a warning: the object was deleted again before the message
 * was handled, or it is not visible yet because the flush ran inside a transaction
 * that was not committed when the message was handled.
 *
 * Identifiers are loaded in chunks of the configured batch size, which keeps each
 * query within the bind-parameter limits of the database.
 */
final class IndexSearchablesMessageHandler
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly SearchManagerInterface $searchManager,
        private readonly DataProviderRegistryInterface $dataProviderRegistry,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
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

                    $this->logMissingIdentifiers($indexName, $class, $chunk, $objects, $dataProvider);

                    if ([] === $objects) {
                        continue;
                    }

                    $this->searchManager->index($objects);
                }
            }
        }
    }

    /**
     * @param list<int|string>              $identifiers
     * @param list<object>                  $objects
     * @param DataProviderInterface<object> $dataProvider
     */
    private function logMissingIdentifiers(string $indexName, string $class, array $identifiers, array $objects, DataProviderInterface $dataProvider): void
    {
        $missing = array_fill_keys(array_map('strval', $identifiers), true);

        foreach ($objects as $object) {
            unset($missing[(string) $dataProvider->normalizeIdentifiers($dataProvider->getIdentifierValues($object))]);
        }

        if ([] === $missing) {
            return;
        }

        $this->logger->warning('Objects to index could not be loaded: they were deleted since, or the flush ran inside a transaction that was not committed yet when the message was handled.', [
            'index' => $indexName,
            'class' => $class,
            'identifiers' => array_map('strval', array_keys($missing)),
        ]);
    }
}
