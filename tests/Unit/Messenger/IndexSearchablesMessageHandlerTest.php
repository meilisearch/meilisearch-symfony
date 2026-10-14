<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit\Messenger;

use Meilisearch\Bundle\DataProvider\DataProviderInterface;
use Meilisearch\Bundle\DataProvider\DataProviderRegistryInterface;
use Meilisearch\Bundle\Messenger\IndexSearchablesMessage;
use Meilisearch\Bundle\Messenger\IndexSearchablesMessageHandler;
use Meilisearch\Bundle\SearchManagerInterface;
use Meilisearch\Bundle\Tests\Entity\Actor;
use PHPUnit\Framework\TestCase;

final class IndexSearchablesMessageHandlerTest extends TestCase
{
    public function testObjectsAreLoadedThroughTheDataProviderOfTheIndex(): void
    {
        $actors = [new Actor(1, 'Jack Nicholson'), new Actor(2, 'Marlon Brando')];

        $dataProvider = $this->createMock(DataProviderInterface::class);
        $dataProvider->expects($this->once())
            ->method('loadByIdentifiers')
            ->with([1, 2])
            ->willReturn(new \ArrayIterator(['a' => $actors[0], 'b' => $actors[1]]));

        $registry = $this->createMock(DataProviderRegistryInterface::class);
        $registry->expects($this->once())
            ->method('getDataProvider')
            ->with('actor', Actor::class)
            ->willReturn($dataProvider);

        $searchManager = $this->createMock(SearchManagerInterface::class);
        $searchManager->method('isSearchable')->willReturn(true);
        $searchManager->expects($this->once())->method('index')->with($actors)->willReturn([]);

        (new IndexSearchablesMessageHandler($searchManager, $registry))(
            new IndexSearchablesMessage(['actor' => [Actor::class => [1, 2]]])
        );
    }

    public function testNothingIsIndexedWhenNoObjectIsFound(): void
    {
        $dataProvider = $this->createMock(DataProviderInterface::class);
        $dataProvider->method('loadByIdentifiers')->willReturn([]);

        $registry = $this->createMock(DataProviderRegistryInterface::class);
        $registry->method('getDataProvider')->willReturn($dataProvider);

        $searchManager = $this->createMock(SearchManagerInterface::class);
        $searchManager->method('isSearchable')->willReturn(true);
        $searchManager->expects($this->never())->method('index');

        (new IndexSearchablesMessageHandler($searchManager, $registry))(
            new IndexSearchablesMessage(['actor' => [Actor::class => [99]]])
        );
    }

    public function testNotSearchableClassesAreSkipped(): void
    {
        $registry = $this->createMock(DataProviderRegistryInterface::class);
        $registry->expects($this->never())->method('getDataProvider');

        $searchManager = $this->createMock(SearchManagerInterface::class);
        $searchManager->method('isSearchable')->willReturn(false);
        $searchManager->expects($this->never())->method('index');

        (new IndexSearchablesMessageHandler($searchManager, $registry))(
            new IndexSearchablesMessage(['actor' => [Actor::class => [1]]])
        );
    }
}
