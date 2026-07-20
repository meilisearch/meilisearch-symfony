<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Messenger;

/**
 * Requests the (re)indexing of searchable entities.
 *
 * Only entity class names and their identifiers are transported: the handler
 * reloads the live entities before normalizing them, so the message stays small
 * and serialization-safe across transports.
 */
final class IndexEntitiesMessage
{
    /**
     * @param array<class-string, list<int|string>> $identifiersByClass
     */
    public function __construct(
        private readonly array $identifiersByClass,
    ) {
    }

    /**
     * @return array<class-string, list<int|string>>
     */
    public function getIdentifiersByClass(): array
    {
        return $this->identifiersByClass;
    }
}
