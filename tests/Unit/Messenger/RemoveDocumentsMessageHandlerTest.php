<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit\Messenger;

use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\Messenger\RemoveDocumentsMessage;
use Meilisearch\Bundle\Messenger\RemoveDocumentsMessageHandler;
use Meilisearch\Bundle\SearchManagerInterface;
use Meilisearch\Client;
use Meilisearch\Contracts\Task;
use Meilisearch\Endpoints\Indexes;
use PHPUnit\Framework\TestCase;

final class RemoveDocumentsMessageHandlerTest extends TestCase
{
    public function testDocumentsAreRemovedFromTheIndexWithTheConfiguredPrefix(): void
    {
        $index = $this->createMock(Indexes::class);
        $index->expects($this->once())
            ->method('deleteDocuments')
            ->with([1, 2])
            ->willReturn(Task::fromArray(['taskUid' => 1, 'indexUid' => 'sf_posts', 'status' => 'enqueued', 'type' => 'documentDeletion', 'enqueuedAt' => '2026-01-01T00:00:00Z']));

        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('index')->with('sf_posts')->willReturn($index);

        $searchManager = $this->createStub(SearchManagerInterface::class);
        $searchManager->method('getConfiguration')->willReturn(new Collection(['prefix' => 'sf_']));

        (new RemoveDocumentsMessageHandler($searchManager, new Engine($client)))(
            new RemoveDocumentsMessage(['posts' => [1, 2], 'comments' => []])
        );
    }
}
