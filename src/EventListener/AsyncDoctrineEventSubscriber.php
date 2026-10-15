<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\EventListener;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Meilisearch\Bundle\DataProvider\DataProviderRegistryInterface;
use Meilisearch\Bundle\Messenger\IndexSearchablesMessage;
use Meilisearch\Bundle\Messenger\RemoveDocumentsMessage;
use Meilisearch\Bundle\Model\Aggregator;
use Meilisearch\Bundle\SearchManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Asynchronous replacement for {@see DoctrineEventSubscriber}.
 *
 * Instead of performing one blocking Meilisearch HTTP call per entity inside the
 * request, changes are buffered during the flush and dispatched once per flush as
 * Messenger messages. Routing those messages to an async transport moves the actual
 * indexing/removal out of the request cycle, mirroring how Symfony Mailer makes
 * delivery asynchronous purely through message routing.
 *
 * Removals resolve their document ids while the entity is still alive and delete by
 * id, so the handler never reloads a deleted entity (see #62).
 *
 * @internal
 */
final class AsyncDoctrineEventSubscriber
{
    /**
     * Searchable entity ids to (re)index, deduplicated per index name and class.
     *
     * @var array<non-empty-string, array<class-string, array<array-key, int|string>>>
     */
    private array $entitiesToIndex = [];

    /**
     * Document ids to delete, deduplicated per prefixed index uid.
     *
     * @var array<non-empty-string, array<array-key, int|string>>
     */
    private array $documentsToRemove = [];

    public function __construct(
        private readonly SearchManagerInterface $searchManager,
        private readonly MessageBusInterface $bus,
        private readonly DataProviderRegistryInterface $dataProviderRegistry,
    ) {
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->scheduleIndex($args);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->scheduleIndex($args);
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        // Resolve the document ids now, before the entity is gone from the database.
        // The async handler deletes by id and never reloads the entity.
        $prefix = $this->searchManager->getConfiguration()->get('prefix');

        foreach ($this->resolveDocumentIds($args) as $indexName => $documentId) {
            $this->documentsToRemove[$prefix.$indexName][(string) $documentId] = $documentId;
        }
    }

    public function postFlush(): void
    {
        // Reset the buffers before dispatching, so a failing dispatch can never make a
        // later flush send an already dispatched batch again.
        $entitiesToIndex = $this->entitiesToIndex;
        $documentsToRemove = $this->documentsToRemove;
        $this->entitiesToIndex = [];
        $this->documentsToRemove = [];

        if ([] !== $entitiesToIndex) {
            $this->bus->dispatch(new IndexSearchablesMessage(array_map($this->toLists(...), $entitiesToIndex)));
        }

        if ([] !== $documentsToRemove) {
            $this->bus->dispatch(new RemoveDocumentsMessage($this->toLists($documentsToRemove)));
        }
    }

    private function scheduleIndex(LifecycleEventArgs $args): void
    {
        $documentIds = $this->resolveDocumentIds($args);

        if ([] === $documentIds) {
            return;
        }

        // The handler indexes a reloaded entity into every index it belongs to, so a
        // single index is enough to reload it: its data provider and the id that
        // provider understands are kept together.
        $class = $this->resolveClass($args);
        $indexName = array_key_first($documentIds);
        $documentId = $documentIds[$indexName];

        $this->entitiesToIndex[$indexName][$class][(string) $documentId] = $documentId;
    }

    /**
     * Resolves the Meilisearch document ids the entity maps to, keyed by index name
     * (without prefix), the same way the search manager does when indexing.
     *
     * An entity belongs to an index configured for its class, for one of its parent
     * classes, or for an aggregator listing its class.
     *
     * @return array<non-empty-string, int|string>
     */
    private function resolveDocumentIds(LifecycleEventArgs $args): array
    {
        $entity = $args->getObject();
        $class = $this->resolveClass($args);
        $documentIds = [];

        foreach ($this->searchManager->getConfiguration()->get('indices') as $index) {
            $indexClass = $index['class'];
            $isAggregated = is_subclass_of($indexClass, Aggregator::class) && \in_array($class, $indexClass::getEntities(), true);

            if (!$isAggregated && !is_a($class, $indexClass, true)) {
                continue;
            }

            $provider = $this->dataProviderRegistry->getDataProvider($index['name'], $class);
            $documentIds[$index['name']] = $provider->normalizeIdentifiers($provider->getIdentifierValues($entity));
        }

        return $documentIds;
    }

    /**
     * Resolves the entity class name through the object manager, so proxies are
     * mapped to the class they proxy.
     *
     * @return class-string
     */
    private function resolveClass(LifecycleEventArgs $args): string
    {
        $entity = $args->getObject();

        return $args->getObjectManager()->getClassMetadata($entity::class)->getName();
    }

    /**
     * @param array<string, array<array-key, int|string>> $buckets
     *
     * @return array<string, list<int|string>>
     */
    private function toLists(array $buckets): array
    {
        return array_map(static fn (array $ids): array => array_values($ids), $buckets);
    }
}
