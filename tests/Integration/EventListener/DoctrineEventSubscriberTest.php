<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration\EventListener;

use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Bundle\Tests\Entity\ObjectId\DummyObjectId;
use Meilisearch\Bundle\Tests\Entity\Page;
use Meilisearch\Bundle\Tests\Entity\Post;

class DoctrineEventSubscriberTest extends BaseKernelTestCase
{
    public function testPostPersist(): void
    {
        $post = $this->createPost();

        $this->waitForAllTasks();

        $result = $this->searchService->search($this->entityManager, Post::class, $post->getTitle());

        $this->assertCount(1, $result);
        $this->assertSame($post->getId(), $result[0]->getId());
    }

    public function testPostPersistWithObjectId(): void
    {
        $page = $this->createPage(1);

        $this->waitForAllTasks();

        $result = $this->searchService->search($this->entityManager, Page::class, $page->getTitle());

        $this->assertCount(1, $result);
        $this->assertEquals(new DummyObjectId(1), $result[0]->getId());
    }

    public function testPostUpdate(): void
    {
        $post = $this->createPost();

        $this->waitForAllTasks();

        $post->setTitle('Better post');

        $this->entityManager->flush();

        $this->waitForAllTasks();

        $result = $this->searchService->search($this->entityManager, Post::class, 'better');

        $this->assertCount(1, $result);
        $this->assertSame($post->getId(), $result[0]->getId());
        $this->assertSame('Better post', $result[0]->getTitle());
    }

    public function testPostUpdateWithObjectId(): void
    {
        $page = $this->createPage(1);

        $this->waitForAllTasks();

        $page->setTitle('Better page');

        $this->entityManager->flush();

        $this->waitForAllTasks();

        $result = $this->searchService->search($this->entityManager, Page::class, 'better');

        $this->assertCount(1, $result);
        $this->assertEquals(new DummyObjectId(1), $result[0]->getId());
        $this->assertSame('Better page', $result[0]->getTitle());
    }

    public function testPreRemove(): void
    {
        $post = $this->createPost();

        $this->waitForAllTasks();

        $result = $this->searchService->search($this->entityManager, Post::class, $post->getTitle());

        $this->assertCount(1, $result);
        $this->assertSame($post->getId(), $result[0]->getId());

        $this->entityManager->remove($post);
        $this->entityManager->flush();

        $this->waitForAllTasks();

        $result = $this->searchService->search($this->entityManager, Post::class, $post->getTitle());

        $this->assertCount(0, $result);
    }

    public function testPreRemoveWithObjectId(): void
    {
        $page = $this->createPage(1);

        $this->waitForAllTasks();

        $result = $this->searchService->search($this->entityManager, Page::class, $page->getTitle());

        $this->assertCount(1, $result);
        $this->assertEquals($page->getId(), $result[0]->getId());

        $this->entityManager->remove($page);
        $this->entityManager->flush();

        $this->waitForAllTasks();

        $result = $this->searchService->search($this->entityManager, Page::class, $page->getTitle());

        $this->assertCount(0, $result);
    }
}
