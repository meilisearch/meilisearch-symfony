<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit\EventListener;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\Event\ManagerEventArgs;
use Doctrine\Persistence\Event\OnClearEventArgs;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\DataProvider\DataProviderInterface;
use Meilisearch\Bundle\DataProvider\DataProviderRegistryInterface;
use Meilisearch\Bundle\EventListener\AsyncDoctrineEventSubscriber;
use Meilisearch\Bundle\Messenger\IndexSearchablesMessage;
use Meilisearch\Bundle\Messenger\RemoveDocumentsMessage;
use Meilisearch\Bundle\SearchManagerInterface;
use Meilisearch\Bundle\Tests\Entity\Comment;
use Meilisearch\Bundle\Tests\Entity\ContentAggregator;
use Meilisearch\Bundle\Tests\Entity\Post;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class AsyncDoctrineEventSubscriberTest extends TestCase
{
    private ObjectManager $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = $this->createObjectManager();
    }

    /**
     * The entity class has no index of its own: it is only part of an aggregator.
     */
    public function testEntityOnlyPartOfAnAggregatorIsIndexed(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with(new IndexSearchablesMessage(['aggregated' => [Post::class => [12]]]))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $subscriber = $this->createSubscriber($bus);
        $subscriber->postPersist($this->createEventArgs(new Post('Aggregated post')));
        $subscriber->postFlush(new ManagerEventArgs($this->objectManager));
    }

    public function testEntityOnlyPartOfAnAggregatorIsRemoved(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with(new RemoveDocumentsMessage(['aggregated' => [12]]))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $subscriber = $this->createSubscriber($bus);
        $subscriber->preRemove($this->createEventArgs(new Post('Aggregated post')));
        $subscriber->postFlush(new ManagerEventArgs($this->objectManager));
    }

    public function testFailingDispatchDoesNotResendAnAlreadyDispatchedBatch(): void
    {
        $dispatched = [];

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
            $dispatched[] = $message;

            if ($message instanceof RemoveDocumentsMessage) {
                throw new \RuntimeException('Transport unavailable');
            }

            return new Envelope($message);
        });

        $subscriber = $this->createSubscriber($bus);
        $subscriber->postPersist($this->createEventArgs(new Post('Indexed post')));
        $subscriber->preRemove($this->createEventArgs(new Post('Removed post')));

        $thrown = false;

        try {
            $subscriber->postFlush(new ManagerEventArgs($this->objectManager));
        } catch (\RuntimeException) {
            $thrown = true;
        }

        $this->assertTrue($thrown, 'The failing dispatch should not be swallowed.');

        $subscriber->postFlush(new ManagerEventArgs($this->objectManager));

        $this->assertEquals([
            new IndexSearchablesMessage(['aggregated' => [Post::class => [12]]]),
            new RemoveDocumentsMessage(['aggregated' => [12]]),
        ], $dispatched);
    }

    public function testFailingIndexDispatchStillDispatchesTheRemovals(): void
    {
        $dispatched = [];

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
            $dispatched[] = $message;

            if ($message instanceof IndexSearchablesMessage) {
                throw new \RuntimeException('Message cannot be sent');
            }

            return new Envelope($message);
        });

        $subscriber = $this->createSubscriber($bus);
        $subscriber->postPersist($this->createEventArgs(new Post('Indexed post')));
        $subscriber->preRemove($this->createEventArgs(new Post('Removed post')));

        $thrown = false;

        try {
            $subscriber->postFlush(new ManagerEventArgs($this->objectManager));
        } catch (\RuntimeException) {
            $thrown = true;
        }

        $this->assertTrue($thrown, 'The failing dispatch should not be swallowed.');

        $subscriber->postFlush(new ManagerEventArgs($this->objectManager));

        $this->assertEquals([
            new IndexSearchablesMessage(['aggregated' => [Post::class => [12]]]),
            new RemoveDocumentsMessage(['aggregated' => [12]]),
        ], $dispatched);
    }

    /**
     * The removal is buffered on remove(), but the flush fails (e.g. on a foreign key
     * constraint): a later flush in the same process must not dispatch it.
     */
    public function testResetDropsChangesOfAFailedFlush(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $subscriber = $this->createSubscriber($bus);
        $subscriber->postPersist($this->createEventArgs(new Post('Indexed post')));
        $subscriber->preRemove($this->createEventArgs(new Post('Removed post')));
        $subscriber->reset();
        $subscriber->postFlush(new ManagerEventArgs($this->objectManager));
    }

    public function testClearingTheObjectManagerDropsPendingChanges(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $subscriber = $this->createSubscriber($bus);
        $subscriber->preRemove($this->createEventArgs(new Post('Removed post')));
        $subscriber->onClear(new OnClearEventArgs($this->objectManager));
        $subscriber->postFlush(new ManagerEventArgs($this->objectManager));
    }

    /**
     * The subscriber is shared by all object managers: a flush or clear of one of
     * them must leave the pending changes of the others alone.
     */
    public function testPendingChangesAreKeptPerObjectManager(): void
    {
        $dispatched = [];

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
            $dispatched[] = $message;

            return new Envelope($message);
        });

        $otherObjectManager = $this->createObjectManager();

        $subscriber = $this->createSubscriber($bus);
        $subscriber->preRemove($this->createEventArgs(new Post('Removed post')));
        $subscriber->postPersist($this->createEventArgs(new Post('Indexed post'), $otherObjectManager));

        $subscriber->postFlush(new ManagerEventArgs($otherObjectManager));
        $subscriber->onClear(new OnClearEventArgs($otherObjectManager));

        $this->assertEquals([new IndexSearchablesMessage(['aggregated' => [Post::class => [12]]])], $dispatched);

        $subscriber->postFlush(new ManagerEventArgs($this->objectManager));

        $this->assertEquals([
            new IndexSearchablesMessage(['aggregated' => [Post::class => [12]]]),
            new RemoveDocumentsMessage(['aggregated' => [12]]),
        ], $dispatched);
    }

    public function testEntityWithoutIndexIsIgnored(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $subscriber = $this->createSubscriber($bus);
        $subscriber->postPersist($this->createEventArgs(new Comment(new Post(), 'Not indexed')));
        $subscriber->preRemove($this->createEventArgs(new Comment(new Post(), 'Not indexed')));
        $subscriber->postFlush(new ManagerEventArgs($this->objectManager));
    }

    private function createSubscriber(MessageBusInterface $bus): AsyncDoctrineEventSubscriber
    {
        $searchManager = $this->createStub(SearchManagerInterface::class);
        $searchManager->method('getConfiguration')->willReturn(new Collection([
            'prefix' => 'sf_',
            'indices' => [
                ['name' => 'aggregated', 'class' => ContentAggregator::class],
            ],
        ]));

        $dataProvider = $this->createStub(DataProviderInterface::class);
        $dataProvider->method('getIdentifierValues')->willReturn(['id' => 12]);
        $dataProvider->method('normalizeIdentifiers')->willReturn(12);

        $registry = $this->createMock(DataProviderRegistryInterface::class);
        $registry->method('getDataProvider')->with('aggregated', Post::class)->willReturn($dataProvider);

        return new AsyncDoctrineEventSubscriber($searchManager, $bus, $registry);
    }

    private function createObjectManager(): ObjectManager
    {
        $objectManager = $this->createStub(ObjectManager::class);
        $objectManager->method('getClassMetadata')->willReturnCallback(function (string $class): ClassMetadata {
            $metadata = $this->createStub(ClassMetadata::class);
            $metadata->method('getName')->willReturn($class);

            return $metadata;
        });

        return $objectManager;
    }

    private function createEventArgs(object $entity, ?ObjectManager $objectManager = null): LifecycleEventArgs
    {
        return new LifecycleEventArgs($entity, $objectManager ?? $this->objectManager);
    }
}
