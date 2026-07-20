<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Messenger;

/**
 * Requests the removal of documents from Meilisearch indexes.
 *
 * Documents are addressed by their already-resolved id, grouped per prefixed
 * index uid. The entity is *not* transported and never reloaded: removal only
 * needs the document id, which avoids the "entity not found" failure that
 * happens when a deleted entity is re-fetched and normalized (see #62).
 */
final class RemoveDocumentsMessage
{
    /**
     * @param array<non-empty-string, list<int|string>> $documentIdsByIndexUid Prefixed index uid => list of document ids
     */
    public function __construct(
        private readonly array $documentIdsByIndexUid,
    ) {
    }

    /**
     * @return array<non-empty-string, list<int|string>>
     */
    public function getDocumentIdsByIndexUid(): array
    {
        return $this->documentIdsByIndexUid;
    }
}
