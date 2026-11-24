<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Messenger;

/**
 * Requests the (re)indexing of searchable objects.
 *
 * Only index names, class names and identifiers are transported: the handler
 * reloads the live objects through the data provider of the given index before
 * normalizing them, so the message stays small and serialization-safe across
 * transports.
 *
 * The index name is only used to resolve the data provider that loads the
 * objects; once loaded they are indexed into every index they belong to.
 */
final class IndexSearchablesMessage
{
    /**
     * @param array<non-empty-string, array<class-string, list<int|string>>> $identifiersByIndex Index name (without prefix) => class name => normalized identifiers
     */
    public function __construct(
        private readonly array $identifiersByIndex,
    ) {
    }

    /**
     * @return array<non-empty-string, array<class-string, list<int|string>>>
     */
    public function getIdentifiersByIndex(): array
    {
        return $this->identifiersByIndex;
    }
}
