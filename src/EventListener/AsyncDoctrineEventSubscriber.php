<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\EventListener;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Meilisearch\Bundle\Messenger\IndexEntitiesMessage;
use Meilisearch\Bundle\Messenger\RemoveDocumentsMessage;
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
 */
final class AsyncDoctrineEventSubscriber
{
    /**
     * Searchable entity ids to (re)index, deduplicated per class.
     *
     * @var array<class-string, array<array-key, int|string>>
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
        foreach ($this->searchManager->getSearchableDocumentIds($args->getObject()) as $indexUid => $documentId) {
            $this->documentsToRemove[$indexUid][(string) $documentId] = $documentId;
        }
    }

    public function postFlush(): void
    {
        if ([] !== $this->entitiesToIndex) {
            $this->bus->dispatch(new IndexEntitiesMessage($this->toLists($this->entitiesToIndex)));
        }

        if ([] !== $this->documentsToRemove) {
            $this->bus->dispatch(new RemoveDocumentsMessage($this->toLists($this->documentsToRemove)));
        }

        $this->entitiesToIndex = [];
        $this->documentsToRemove = [];
    }

    private function scheduleIndex(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->searchManager->isSearchable($entity)) {
            return;
        }

        $documentIds = $this->searchManager->getSearchableDocumentIds($entity);

        if ([] === $documentIds) {
            return;
        }

        // The entity maps to the same document id in every index it belongs to, so any
        // of them can be used to reload it later. The class name resolves proxies.
        $class = $args->getObjectManager()->getClassMetadata($entity::class)->getName();
        $documentId = reset($documentIds);

        $this->entitiesToIndex[$class][(string) $documentId] = $documentId;
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
