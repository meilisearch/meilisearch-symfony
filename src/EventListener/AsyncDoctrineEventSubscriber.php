<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\EventListener;

use Doctrine\Common\EventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use Meilisearch\Bundle\DataProvider\DataProviderRegistryInterface;
use Meilisearch\Bundle\Messenger\IndexSearchablesMessage;
use Meilisearch\Bundle\Messenger\RemoveDocumentsMessage;
use Meilisearch\Bundle\Model\Aggregator;
use Meilisearch\Bundle\SearchManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ResetInterface;

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
 * id, so the handler never reloads a deleted entity (see #62). They are only queued
 * for dispatch once the entity has actually been deleted, so a removal that is never
 * flushed never reaches the bus.
 *
 * Changes are buffered per object manager: a flush only dispatches the changes of
 * the manager that flushed, and clearing a manager only drops its own changes. All
 * buffers are cleared when the service is reset, so changes of a failed flush are
 * never dispatched by a later flush in a long-running process.
 *
 * @internal
 */
final class AsyncDoctrineEventSubscriber implements ResetInterface
{
    /**
     * Pending changes per object manager: searchable entity ids to (re)index,
     * deduplicated per index name and class, and document ids to delete,
     * deduplicated per class and index name. Both are kept per class, so a partial
     * clear of the object manager only drops the changes of the cleared classes.
     *
     * @var \WeakMap<ObjectManager, array{
     *     index: array<non-empty-string, array<class-string, array<array-key, int|string>>>,
     *     remove: array<class-string, array<non-empty-string, array<array-key, int|string>>>,
     * }>
     */
    private \WeakMap $pending;

    /**
     * Document ids resolved in preRemove, per entity, until postRemove confirms that
     * the entity has been deleted.
     *
     * @var \WeakMap<object, array{class: class-string, documentIds: array<non-empty-string, int|string>}>
     */
    private \WeakMap $removing;

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly SearchManagerInterface $searchManager,
        private readonly MessageBusInterface $bus,
        private readonly DataProviderRegistryInterface $dataProviderRegistry,
        ?LoggerInterface $logger = null,
    ) {
        $this->pending = new \WeakMap();
        $this->removing = new \WeakMap();
        $this->logger = $logger ?? new NullLogger();
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
        // Resolve the document ids now, while the entity is still alive: after the
        // delete its identifier may already be gone. The async handler deletes by id
        // and never reloads the entity.
        $documentIds = $this->resolveDocumentIds($args);

        if ([] === $documentIds) {
            return;
        }

        $this->removing[$args->getObject()] = [
            'class' => $this->resolveClass($args),
            'documentIds' => $documentIds,
        ];
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        // preRemove runs on remove(), postRemove only once the entity has been deleted:
        // a removal that is never flushed is never dispatched.
        $entity = $args->getObject();

        if (!isset($this->removing[$entity])) {
            return;
        }

        ['class' => $class, 'documentIds' => $documentIds] = $this->removing[$entity];
        unset($this->removing[$entity]);

        $manager = $args->getObjectManager();
        $pending = $this->pending[$manager] ?? ['index' => [], 'remove' => []];

        foreach ($documentIds as $indexName => $documentId) {
            $pending['remove'][$class][$indexName][(string) $documentId] = $documentId;
        }

        $this->pending[$manager] = $pending;
    }

    public function onClear(EventArgs $args): void
    {
        // Pending changes of cleared entities will never be flushed.
        $manager = self::getObjectManager($args);

        // doctrine/orm < 3 and doctrine/mongodb-odm can clear the entities of a single
        // class only; the changes of other classes must then be kept.
        $clearedClass = method_exists($args, 'getEntityClass') ? $args->getEntityClass() : null;

        if (null === $clearedClass) {
            unset($this->pending[$manager]);

            return;
        }

        if (!isset($this->pending[$manager])) {
            return;
        }

        $pending = $this->pending[$manager];

        foreach ($pending['index'] as $indexName => $identifiersByClass) {
            foreach (array_keys($identifiersByClass) as $class) {
                if (is_a($class, $clearedClass, true)) {
                    unset($pending['index'][$indexName][$class]);
                }
            }

            if ([] === $pending['index'][$indexName]) {
                unset($pending['index'][$indexName]);
            }
        }

        foreach (array_keys($pending['remove']) as $class) {
            if (is_a($class, $clearedClass, true)) {
                unset($pending['remove'][$class]);
            }
        }

        $this->pending[$manager] = $pending;
    }

    public function reset(): void
    {
        $this->pending = new \WeakMap();
        $this->removing = new \WeakMap();
    }

    public function postFlush(EventArgs $args): void
    {
        $manager = self::getObjectManager($args);
        $pending = $this->pending[$manager] ?? null;

        // Clear the buffers before dispatching, so a failing dispatch can never make a
        // later flush send an already dispatched batch again.
        unset($this->pending[$manager]);

        if (null === $pending) {
            return;
        }

        $entitiesToIndex = $pending['index'];
        $documentsToRemove = [];

        foreach ($pending['remove'] as $documentIdsByIndex) {
            foreach ($documentIdsByIndex as $indexName => $documentIds) {
                $documentsToRemove[$indexName] = ($documentsToRemove[$indexName] ?? []) + $documentIds;
            }
        }

        if ([] !== $entitiesToIndex) {
            $this->dispatch(new IndexSearchablesMessage(array_map($this->toLists(...), $entitiesToIndex)));
        }

        if ([] !== $documentsToRemove) {
            $this->dispatch(new RemoveDocumentsMessage($this->toLists($documentsToRemove)));
        }
    }

    /**
     * Dispatches a message without letting a failure escape: postFlush runs after the
     * commit, so an exception would make the caller of flush() treat committed changes
     * as failed. The failure is logged instead.
     */
    private function dispatch(object $message): void
    {
        try {
            $this->bus->dispatch($message);
        } catch (\Throwable $exception) {
            $this->logger->error('Dispatching "{message}" failed after a successful flush; the affected Meilisearch documents are out of date until they are reindexed.', [
                'message' => $message::class,
                'exception' => $exception,
            ]);
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
        // provider understands are kept together. The first configured index is
        // deliberately the one used, so an entity in several indices with different
        // data providers is always reloaded through the provider of that index.
        $class = $this->resolveClass($args);
        $indexName = array_key_first($documentIds);
        $documentId = $documentIds[$indexName];

        $manager = $args->getObjectManager();
        $pending = $this->pending[$manager] ?? ['index' => [], 'remove' => []];
        $pending['index'][$indexName][$class][(string) $documentId] = $documentId;
        $this->pending[$manager] = $pending;
    }

    /**
     * Returns the object manager of a postFlush or onClear event.
     */
    private static function getObjectManager(EventArgs $args): ObjectManager
    {
        if (method_exists($args, 'getObjectManager')) {
            return $args->getObjectManager();
        }

        // doctrine/orm < 2.13 only exposes the entity manager on these events.
        \assert(method_exists($args, 'getEntityManager'));

        return $args->getEntityManager();
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
