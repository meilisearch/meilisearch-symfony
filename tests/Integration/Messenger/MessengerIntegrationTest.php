<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration\Messenger;

use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Bundle\Tests\Entity\Article;
use Meilisearch\Bundle\Tests\Entity\Car;
use Meilisearch\Bundle\Tests\Entity\ContentAggregator;
use Meilisearch\Bundle\Tests\Entity\ContentItem;
use Meilisearch\Bundle\Tests\Entity\ObjectId\DummyObjectId;
use Meilisearch\Bundle\Tests\Entity\Page;
use Meilisearch\Bundle\Tests\Entity\Post;
use Meilisearch\Bundle\Tests\MessengerKernel;

/**
 * Exercises the optional Messenger integration end-to-end against a real Meilisearch.
 *
 * The kernel routes both messages to an in-process (sync) transport, so a persist/
 * remove dispatches a message that is handled immediately — proving the opt-in path
 * performs the indexing/removal instead of the synchronous Doctrine subscriber.
 */
final class MessengerIntegrationTest extends BaseKernelTestCase
{
    protected static function getKernelClass(): string
    {
        return MessengerKernel::class;
    }

    public function testEntityIsIndexedThroughMessenger(): void
    {
        $post = new Post('Async indexed post');

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $this->waitForAllTasks();

        $result = $this->searchManager->search(Post::class, 'Async');

        $this->assertCount(1, $result);
        $this->assertSame($post->getId(), $result[0]->getId());
    }

    public function testEntityUpdateIsIndexedThroughMessenger(): void
    {
        $post = new Post('First title');

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $this->waitForAllTasks();

        $post->setTitle('Second title');
        $this->entityManager->flush();

        $this->waitForAllTasks();

        $result = $this->searchManager->search(Post::class, 'Second');

        $this->assertCount(1, $result);
        $this->assertSame('Second title', $result[0]->getTitle());
    }

    /**
     * The handler reloads entities through the data provider of the index, so
     * composite identifiers are supported just like in the synchronous path.
     */
    public function testEntityWithCompositeIdentifierIsIndexedThroughMessenger(): void
    {
        $this->entityManager->persist(new Car('Audi A8', 2010));
        $this->entityManager->persist(new Car('Audi A8', 2012));
        $this->entityManager->flush();

        $this->waitForAllTasks();

        $this->assertCount(2, $this->searchManager->search(Car::class, 'Audi'));
    }

    public function testEntityIsIndexedIntoEveryIndexItBelongsTo(): void
    {
        $post = new Post('Aggregated async post');

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $this->waitForAllTasks();

        $this->assertCount(1, $this->searchManager->search(Post::class, 'Aggregated'));
        $this->assertSame(1, $this->searchManager->count(ContentAggregator::class, 'Aggregated'));
    }

    /**
     * The index is configured for the parent class only, so the entity is not
     * "searchable" by its own class name but still has to be indexed and removed.
     */
    public function testEntityIndexedThroughItsParentClassIsIndexedAndRemovedThroughMessenger(): void
    {
        $article = new Article();

        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->waitForAllTasks();

        $this->assertSame(1, $this->searchManager->count(ContentItem::class));

        $this->entityManager->remove($article);
        $this->entityManager->flush();

        $this->waitForAllTasks();

        $this->assertSame(0, $this->searchManager->count(ContentItem::class));
    }

    /**
     * Regression test for meilisearch-symfony#62: removing an entity must delete its
     * document without the handler re-fetching the (now deleted) entity, which used
     * to fail with an "entity not found" error.
     */
    public function testEntityRemovalThroughMessengerResolvesIssue62(): void
    {
        $post = new Post('Post to remove');

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $this->waitForAllTasks();

        $this->assertCount(1, $this->searchManager->search(Post::class, 'remove'));

        $this->entityManager->remove($post);
        $this->entityManager->flush();

        $this->waitForAllTasks();

        $this->assertCount(0, $this->searchManager->search(Post::class, 'remove'));
    }

    public function testEntityWithObjectIdRemovalThroughMessenger(): void
    {
        $page = new Page(new DummyObjectId(1));

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        $this->waitForAllTasks();

        $this->assertCount(1, $this->searchManager->search(Page::class, $page->getTitle()));

        $this->entityManager->remove($page);
        $this->entityManager->flush();

        $this->waitForAllTasks();

        $this->assertCount(0, $this->searchManager->search(Page::class, $page->getTitle()));
    }
}
