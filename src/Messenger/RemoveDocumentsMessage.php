<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Messenger;

/**
 * Requests the removal of documents from Meilisearch indexes.
 *
 * Documents are addressed by their already-resolved id, grouped per index name
 * (without prefix): the handler adds the configured prefix when it handles the
 * message, like the indexing path does. The entity is *not* transported and never reloaded: removal only
 * needs the document id, which avoids the "entity not found" failure that
 * happens when a deleted entity is re-fetched and normalized (see #62).
 */
final class RemoveDocumentsMessage
{
    /**
     * @param array<non-empty-string, list<int|string>> $documentIdsByIndex Index name (without prefix) => list of document ids
     */
    public function __construct(
        private readonly array $documentIdsByIndex,
    ) {
    }

    /**
     * @return array<non-empty-string, list<int|string>>
     */
    public function getDocumentIdsByIndex(): array
    {
        return $this->documentIdsByIndex;
    }
}
