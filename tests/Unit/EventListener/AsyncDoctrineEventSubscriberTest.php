<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit\EventListener;

use Doctrine\Persistence\Event\LifecycleEventArgs;
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
        $subscriber->postFlush();
    }

    public function testEntityOnlyPartOfAnAggregatorIsRemoved(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with(new RemoveDocumentsMessage(['sf_aggregated' => [12]]))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $subscriber = $this->createSubscriber($bus);
        $subscriber->preRemove($this->createEventArgs(new Post('Aggregated post')));
        $subscriber->postFlush();
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

        try {
            $subscriber->postFlush();
            $this->fail('The failing dispatch should not be swallowed.');
        } catch (\RuntimeException) {
        }

        $subscriber->postFlush();

        $this->assertEquals([
            new IndexSearchablesMessage(['aggregated' => [Post::class => [12]]]),
            new RemoveDocumentsMessage(['sf_aggregated' => [12]]),
        ], $dispatched);
    }

    public function testEntityWithoutIndexIsIgnored(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $subscriber = $this->createSubscriber($bus);
        $subscriber->postPersist($this->createEventArgs(new Comment(new Post(), 'Not indexed')));
        $subscriber->preRemove($this->createEventArgs(new Comment(new Post(), 'Not indexed')));
        $subscriber->postFlush();
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

    private function createEventArgs(object $entity): LifecycleEventArgs
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getName')->willReturn($entity::class);

        $objectManager = $this->createStub(ObjectManager::class);
        $objectManager->method('getClassMetadata')->willReturn($metadata);

        return new LifecycleEventArgs($entity, $objectManager);
    }
}
