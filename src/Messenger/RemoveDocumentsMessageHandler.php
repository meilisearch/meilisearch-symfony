<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Messenger;

use Meilisearch\Bundle\Engine;

/**
 * Deletes the documents referenced by a {@see RemoveDocumentsMessage} directly by id.
 *
 * The source entities are already gone by the time the message is handled, so no
 * reload or normalization happens: only the previously resolved document ids are
 * used, which is what avoids the "entity not found" failure described in #62.
 */
final class RemoveDocumentsMessageHandler
{
    public function __construct(
        private readonly Engine $engine,
    ) {
    }

    public function __invoke(RemoveDocumentsMessage $message): void
    {
        foreach ($message->getDocumentIdsByIndexUid() as $indexUid => $ids) {
            if ([] === $ids) {
                continue;
            }

            $this->engine->removeDocuments($indexUid, $ids);
        }
    }
}
