<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Messenger;

use Doctrine\Persistence\ManagerRegistry;
use Meilisearch\Bundle\SearchManagerInterface;

/**
 * Reloads the live entities referenced by an {@see IndexEntitiesMessage} and hands
 * them to the search manager for (re)indexing.
 *
 * Entities are looked up by their (single-field) identifier through Doctrine: the
 * async indexing path is only ever fed by Doctrine flush events, so a managed
 * entity always exists behind the id. Ids that no longer resolve to an entity are
 * silently skipped (e.g. deleted again before the message was consumed).
 */
final class IndexEntitiesMessageHandler
{
    public function __construct(
        private readonly SearchManagerInterface $searchManager,
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    public function __invoke(IndexEntitiesMessage $message): void
    {
        foreach ($message->getIdentifiersByClass() as $class => $ids) {
            if ([] === $ids || !$this->searchManager->isSearchable($class)) {
                continue;
            }

            $manager = $this->managerRegistry->getManagerForClass($class);

            if (null === $manager) {
                continue;
            }

            $identifierFieldNames = $manager->getClassMetadata($class)->getIdentifierFieldNames();

            // Composite identifiers are out of scope for the async index path; the id
            // shape (int|string) mirrors a single-column identifier.
            if (1 !== \count($identifierFieldNames)) {
                continue;
            }

            $entities = $manager->getRepository($class)->findBy([$identifierFieldNames[0] => $ids]);

            if ([] === $entities) {
                continue;
            }

            $this->searchManager->index($entities);
        }
    }
}
