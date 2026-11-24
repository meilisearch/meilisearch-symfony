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
 * removed from the same indices the consuming process indexes into. Documents are
 * removed in chunks of the configured batch size.
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
        $configuration = $this->searchManager->getConfiguration();
        $prefix = $configuration->get('prefix');
        $batchSize = $configuration->get('batchSize');

        foreach ($message->getDocumentIdsByIndex() as $indexName => $ids) {
            foreach (array_chunk($ids, $batchSize) as $chunk) {
                $this->engine->removeDocuments($prefix.$indexName, $chunk);
            }
        }
    }
}
