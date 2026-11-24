<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit\Messenger;

use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\DataProvider\DataProviderInterface;
use Meilisearch\Bundle\DataProvider\DataProviderRegistryInterface;
use Meilisearch\Bundle\Messenger\IndexSearchablesMessage;
use Meilisearch\Bundle\Messenger\IndexSearchablesMessageHandler;
use Meilisearch\Bundle\SearchManagerInterface;
use Meilisearch\Bundle\Tests\Entity\Actor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class IndexSearchablesMessageHandlerTest extends TestCase
{
    public function testObjectsAreLoadedThroughTheDataProviderOfTheIndex(): void
    {
        $actors = [new Actor(1, 'Jack Nicholson'), new Actor(2, 'Marlon Brando')];

        $dataProvider = $this->createMock(DataProviderInterface::class);
        $dataProvider->method('getIdentifierValues')->willReturnCallback(static fn (Actor $actor): array => ['id' => $actor->id]);
        $dataProvider->method('normalizeIdentifiers')->willReturnCallback(static fn (array $identifiers): int => $identifiers['id']);
        $dataProvider->expects($this->once())
            ->method('loadByIdentifiers')
            ->with([1, 2])
            ->willReturn(new \ArrayIterator(['a' => $actors[0], 'b' => $actors[1]]));

        $registry = $this->createMock(DataProviderRegistryInterface::class);
        $registry->expects($this->once())
            ->method('getDataProvider')
            ->with('actor', Actor::class)
            ->willReturn($dataProvider);

        $searchManager = $this->createSearchManager();
        $searchManager->expects($this->once())->method('index')->with($actors)->willReturn([]);

        (new IndexSearchablesMessageHandler($searchManager, $registry))(
            new IndexSearchablesMessage(['actor' => [Actor::class => [1, 2]]])
        );
    }

    public function testIdentifiersThatResolveToNothingAreLoggedAndSkipped(): void
    {
        $dataProvider = $this->createMock(DataProviderInterface::class);
        $dataProvider->method('loadByIdentifiers')->willReturn([]);

        $registry = $this->createMock(DataProviderRegistryInterface::class);
        $registry->method('getDataProvider')->willReturn($dataProvider);

        $searchManager = $this->createSearchManager();
        $searchManager->expects($this->never())->method('index');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->anything(), ['index' => 'actor', 'class' => Actor::class, 'identifiers' => ['99']]);

        (new IndexSearchablesMessageHandler($searchManager, $registry, $logger))(
            new IndexSearchablesMessage(['actor' => [Actor::class => [99]]])
        );
    }

    /**
     * An identifier that resolves to nothing is ambiguous: deleted since, or not
     * visible yet because the flush ran inside an uncommitted outer transaction.
     * It is logged, while the objects that were found are still indexed.
     */
    public function testOnlyTheIdentifiersThatResolveToNothingAreLogged(): void
    {
        $actor = new Actor(1, 'Jack Nicholson');

        $dataProvider = $this->createMock(DataProviderInterface::class);
        $dataProvider->method('getIdentifierValues')->willReturnCallback(static fn (Actor $actor): array => ['id' => $actor->id]);
        $dataProvider->method('normalizeIdentifiers')->willReturnCallback(static fn (array $identifiers): int => $identifiers['id']);
        $dataProvider->method('loadByIdentifiers')->willReturn([$actor]);

        $registry = $this->createMock(DataProviderRegistryInterface::class);
        $registry->method('getDataProvider')->willReturn($dataProvider);

        $searchManager = $this->createSearchManager();
        $searchManager->expects($this->once())->method('index')->with([$actor])->willReturn([]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->anything(), ['index' => 'actor', 'class' => Actor::class, 'identifiers' => ['2', '3']]);

        (new IndexSearchablesMessageHandler($searchManager, $registry, $logger))(
            new IndexSearchablesMessage(['actor' => [Actor::class => [1, 2, 3]]])
        );
    }

    public function testEmptyIdentifierListsAreSkipped(): void
    {
        $registry = $this->createMock(DataProviderRegistryInterface::class);
        $registry->expects($this->never())->method('getDataProvider');

        $searchManager = $this->createSearchManager();
        $searchManager->expects($this->never())->method('index');

        (new IndexSearchablesMessageHandler($searchManager, $registry))(
            new IndexSearchablesMessage(['actor' => [Actor::class => []]])
        );
    }

    /**
     * Identifiers are loaded in chunks of the configured batch size, so a large flush
     * never exceeds the bind-parameter limits of the database in a single query.
     */
    public function testIdentifiersAreLoadedInChunksOfTheBatchSize(): void
    {
        $actors = [new Actor(1, 'Jack Nicholson'), new Actor(2, 'Marlon Brando'), new Actor(3, 'Robert De Niro')];
        $loaded = [];

        $dataProvider = $this->createMock(DataProviderInterface::class);
        $dataProvider->method('getIdentifierValues')->willReturnCallback(static fn (Actor $actor): array => ['id' => $actor->id]);
        $dataProvider->method('normalizeIdentifiers')->willReturnCallback(static fn (array $identifiers): int => $identifiers['id']);
        $dataProvider->expects($this->exactly(2))
            ->method('loadByIdentifiers')
            ->willReturnCallback(static function (array $identifiers) use ($actors, &$loaded): array {
                $loaded[] = $identifiers;

                return array_values(array_filter($actors, static fn (Actor $actor): bool => \in_array($actor->id, $identifiers, true)));
            });

        $registry = $this->createMock(DataProviderRegistryInterface::class);
        $registry->expects($this->once())->method('getDataProvider')->willReturn($dataProvider);

        $indexed = [];
        $searchManager = $this->createSearchManager(2);
        $searchManager->expects($this->exactly(2))
            ->method('index')
            ->willReturnCallback(static function (array $objects) use (&$indexed): array {
                $indexed[] = $objects;

                return [];
            });

        (new IndexSearchablesMessageHandler($searchManager, $registry))(
            new IndexSearchablesMessage(['actor' => [Actor::class => [1, 2, 3]]])
        );

        $this->assertSame([[1, 2], [3]], $loaded);
        $this->assertSame([[$actors[0], $actors[1]], [$actors[2]]], $indexed);
    }

    private function createSearchManager(int $batchSize = 500): SearchManagerInterface&MockObject
    {
        $searchManager = $this->createMock(SearchManagerInterface::class);
        $searchManager->method('getConfiguration')->willReturn(new Collection(['batchSize' => $batchSize]));

        return $searchManager;
    }
}
