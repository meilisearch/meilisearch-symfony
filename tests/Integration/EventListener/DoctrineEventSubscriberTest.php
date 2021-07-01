<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Test\Integration\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use MeiliSearch\Bundle\EventListener\DoctrineEventSubscriber;
use MeiliSearch\Bundle\Test\BaseKernelTestCase;
use MeiliSearch\Bundle\Test\Entity\Post;

class DoctrineEventSubscriberTest extends BaseKernelTestCase
{
    /**
     * This tests creates two posts in the database, but only one is triggered via an event to MS.
     */
    public function testPostPersist(): void
    {
        $this->createPost();
        $post = $this->createPost();

        $eventArgs = new LifecycleEventArgs($post, $this->entityManager);

        $subscriber = new DoctrineEventSubscriber($this->searchService, []);
        $subscriber->postPersist($eventArgs);

        $result = $this->searchService->search($this->entityManager, Post::class, $post->getTitle());

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]->getId());
    }

    /**
     * This tests creates two posts in the database, but only one is triggered via an event to MS.
     */
    public function testPostUpdate(): void
    {
        $this->createPost();
        $post = $this->createPost();

        $eventArgs = new LifecycleEventArgs($post, $this->entityManager);

        $subscriber = new DoctrineEventSubscriber($this->searchService, []);
        $subscriber->postUpdate($eventArgs);

        $result = $this->searchService->search($this->entityManager, Post::class, $post->getTitle());

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]->getId());
    }

    /**
     * This tests creates posts in the database, send it to MS via a trigger. Afterwards Doctrines 'preRemove' event
     * is going to remove that entity from MS.
     */
    public function testPreRemove(): void
    {
        $post = $this->createPost();

        $eventArgs = new LifecycleEventArgs($post, $this->entityManager);

        $subscriber = new DoctrineEventSubscriber($this->searchService, []);
        $subscriber->postPersist($eventArgs);

        $result = $this->searchService->search($this->entityManager, Post::class, $post->getTitle());

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]->getId());

        $subscriber->preRemove($eventArgs);

        /*
         * As the deletion of a document is an asyncronous transaction, we need to wait some seconds
         * till this is executed. This was introduced as with Github actions there was no other option.
         */
        sleep(2);

        $result = $this->searchService->search($this->entityManager, Post::class, $post->getTitle());

        $this->assertCount(0, $result);
    }
}
