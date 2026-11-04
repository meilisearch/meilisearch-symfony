<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Messenger;

use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\SearchManagerInterface;

/**
 * Deletes the documents referenced by a {@see RemoveDocumentsMessage} directly by id.
 *
 * The source entities are already gone by the time the message is handled, so no
 * reload or normalization happens: only the previously resolved document ids are
 * used, which is what avoids the "entity not found" failure described in #62.
 *
 * The configured prefix is added to the index names here, so the documents are
 * removed from the same indices the consuming process indexes into.
 */
final class RemoveDocumentsMessageHandler
{
    public function __construct(
        private readonly SearchManagerInterface $searchManager,
        private readonly Engine $engine,
    ) {
    }

    public function __invoke(RemoveDocumentsMessage $message): void
    {
        $prefix = $this->searchManager->getConfiguration()->get('prefix');

        foreach ($message->getDocumentIdsByIndex() as $indexName => $ids) {
            if ([] === $ids) {
                continue;
            }

            $this->engine->removeDocuments($prefix.$indexName, $ids);
        }
    }
}
