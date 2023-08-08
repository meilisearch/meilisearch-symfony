<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration\EventListener;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Meilisearch\Bundle\EventListener\DoctrineEventSubscriber;
use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Bundle\Tests\Entity\Page;
use Meilisearch\Bundle\Tests\Entity\Post;
use Meilisearch\Client;

class DoctrineEventSubscriberTest extends BaseKernelTestCase
{
    protected Client $client;

    /**
     * @throws \Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->client = $this->get('meilisearch.client');
    }

    /**
     * This tests creates two posts in the database, but only one is triggered via an event to Meilisearch.
     */
    public function testPostPersist(): void
    {
        $this->createPost();
        $post = $this->createPost();

        $eventArgs = new LifecycleEventArgs($post, $this->entityManager);

        $subscriber = new DoctrineEventSubscriber($this->searchService);
        $subscriber->postPersist($eventArgs);

        $this->waitForAllTasks();

        $result = $this->searchService->search($this->entityManager, Post::class, $post->getTitle());

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]->getId());
    }

    public function testPostPersistWithObjectId(): void
    {
        $this->createPage(1);
        $page = $this->createPage(2);

        $eventArgs = new LifecycleEventArgs($page, $this->entityManager);

        $subscriber = new DoctrineEventSubscriber($this->searchService);
        $subscriber->postPersist($eventArgs);

        $this->waitForAllTasks();

        $result = $this->searchService->search($this->entityManager, Page::class, $page->getTitle());

        $this->assertCount(1, $result);
        $this->assertSame((string) $page->getId(), (string) $result[0]->getId());
    }

    /**
     * This tests creates two posts in the database, but only one is triggered via an event to Meilisearch.
     */
    public function testPostUpdate(): void
    {
        $this->createPost();
        $post = $this->createPost();

        $eventArgs = new LifecycleEventArgs($post, $this->entityManager);

        $subscriber = new DoctrineEventSubscriber($this->searchService);
        $subscriber->postUpdate($eventArgs);

        $this->waitForAllTasks();

        $result = $this->searchService->search($this->entityManager, Post::class, $post->getTitle());

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]->getId());
    }

    public function testPostUpdateWithObjectId(): void
    {
        $this->createPage(1);
        $page = $this->createPage(2);

        $eventArgs = new LifecycleEventArgs($page, $this->entityManager);

        $subscriber = new DoctrineEventSubscriber($this->searchService);
        $subscriber->postUpdate($eventArgs);

        $this->waitForAllTasks();

        $result = $this->searchService->search($this->entityManager, Page::class, $page->getTitle());

        $this->assertCount(1, $result);
        $this->assertSame((string) $page->getId(), (string) $result[0]->getId());
    }

    /**
     * This tests creates posts in the database, send it to Meilisearch via a trigger. Afterwards Doctrines 'preRemove' event
     * is going to remove that entity from MS.
     */
    public function testPreRemove(): void
    {
        $post = $this->createPost();

        $eventArgs = new LifecycleEventArgs($post, $this->entityManager);

        $subscriber = new DoctrineEventSubscriber($this->searchService);
        $subscriber->postPersist($eventArgs);

        $this->waitForAllTasks();

        $result = $this->searchService->search($this->entityManager, Post::class, $post->getTitle());

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]->getId());

        $subscriber->preRemove($eventArgs);

        $this->waitForAllTasks();

        $result = $this->searchService->search($this->entityManager, Post::class, $post->getTitle());

        $this->assertCount(0, $result);
    }

    public function testPreRemoveWithObjectId(): void
    {
        $page = $this->createPage(1);

        $eventArgs = new LifecycleEventArgs($page, $this->entityManager);

        $subscriber = new DoctrineEventSubscriber($this->searchService);
        $subscriber->postPersist($eventArgs);

        $this->waitForAllTasks();

        $result = $this->searchService->search($this->entityManager, Page::class, $page->getTitle());

        $this->assertCount(1, $result);
        $this->assertSame((string) $page->getId(), (string) $result[0]->getId());

        $subscriber->preRemove($eventArgs);

        $this->waitForAllTasks();

        $result = $this->searchService->search($this->entityManager, Page::class, $page->getTitle());

        $this->assertCount(0, $result);
    }

    /**
     * Waits for all the tasks to be finished by checking the topest one (so the newest one).
     */
    private function waitForAllTasks(): void
    {
        $firstTask = $this->client->getTasks()->getResults()[0];
        $this->client->waitForTask($firstTask['uid']);
    }
}
