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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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

    /**
     * postFlush runs after the commit: a failing dispatch must not escape from flush(),
     * must not keep the other message from being dispatched, and must not be resent.
     *
     * @param class-string $failingMessage
     */
    #[DataProvider('provideFailingMessages')]
    public function testFailingDispatchIsLoggedInsteadOfThrown(string $failingMessage): void
    {
        $dispatched = [];

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $message) use (&$dispatched, $failingMessage): Envelope {
            $dispatched[] = $message;

            if ($message instanceof $failingMessage) {
                throw new \RuntimeException('Transport unavailable');
            }

            return new Envelope($message);
        });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->anything(), $this->callback(static fn (array $context): bool => $failingMessage === $context['message'] && $context['exception'] instanceof \RuntimeException));

        $subscriber = $this->createSubscriber($bus, $logger);
        $subscriber->postPersist($this->createEventArgs(new Post('Indexed post')));
        $subscriber->preRemove($this->createEventArgs(new Post('Removed post')));
        $subscriber->postFlush(new ManagerEventArgs($this->objectManager));
        $subscriber->postFlush(new ManagerEventArgs($this->objectManager));

        $this->assertEquals([
            new IndexSearchablesMessage(['aggregated' => [Post::class => [12]]]),
            new RemoveDocumentsMessage(['aggregated' => [12]]),
        ], $dispatched);
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function provideFailingMessages(): iterable
    {
        yield 'index message' => [IndexSearchablesMessage::class];
        yield 'removal message' => [RemoveDocumentsMessage::class];
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

    /**
     * doctrine/orm < 3 can clear the entities of a single class: the pending changes
     * of other classes must be kept, those of the cleared class dropped.
     */
    public function testPartialClearOnlyDropsThePendingChangesOfTheClearedClass(): void
    {
        $dispatched = [];

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
            $dispatched[] = $message;

            return new Envelope($message);
        });

        $subscriber = $this->createSubscriber($bus);
        $subscriber->preRemove($this->createEventArgs(new Post('Removed post')));
        $subscriber->onClear($this->createPartialClearEventArgs(Comment::class));
        $subscriber->postFlush(new ManagerEventArgs($this->objectManager));

        $this->assertEquals([new RemoveDocumentsMessage(['aggregated' => [12]])], $dispatched);

        $subscriber->preRemove($this->createEventArgs(new Post('Removed post')));
        $subscriber->onClear($this->createPartialClearEventArgs(Post::class));
        $subscriber->postFlush(new ManagerEventArgs($this->objectManager));

        $this->assertCount(1, $dispatched);
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

    private function createSubscriber(MessageBusInterface $bus, ?LoggerInterface $logger = null): AsyncDoctrineEventSubscriber
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

        return new AsyncDoctrineEventSubscriber($searchManager, $bus, $registry, $logger);
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

    /**
     * The onClear event of doctrine/orm < 3 when only the entities of one class are cleared.
     *
     * @param class-string $entityClass
     */
    private function createPartialClearEventArgs(string $entityClass): OnClearEventArgs
    {
        return new class($this->objectManager, $entityClass) extends OnClearEventArgs {
            /**
             * @param class-string $entityClass
             */
            public function __construct(ObjectManager $objectManager, private readonly string $entityClass)
            {
                parent::__construct($objectManager);
            }

            public function getEntityClass(): string
            {
                return $this->entityClass;
            }
        };
    }

    private function createEventArgs(object $entity, ?ObjectManager $objectManager = null): LifecycleEventArgs
    {
        return new LifecycleEventArgs($entity, $objectManager ?? $this->objectManager);
    }
}
