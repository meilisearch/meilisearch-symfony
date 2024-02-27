<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\SearchableEntity;
use Meilisearch\Bundle\SearchService;
use Meilisearch\Bundle\Tests\Entity\Comment;
use Meilisearch\Bundle\Tests\Entity\Image;
use Meilisearch\Bundle\Tests\Entity\Link;
use Meilisearch\Bundle\Tests\Entity\ObjectId\DummyObjectId;
use Meilisearch\Bundle\Tests\Entity\Page;
use Meilisearch\Bundle\Tests\Entity\Post;
use Meilisearch\Bundle\Tests\Entity\Tag;
use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class BaseKernelTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;
    protected Client $client;
    protected SearchService $searchService;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = $this->get('doctrine.orm.entity_manager');
        $this->client = $this->get('meilisearch.client');
        $this->searchService = $this->get('meilisearch.service');

        $metaData = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->entityManager);
        $tool->dropSchema($metaData);
        $tool->createSchema($metaData);

        $this->cleanUp();
    }

    protected function createPost(?int $id = null): Post
    {
        $post = new Post();
        $post->setTitle('Test Post');
        $post->setContent('Test content post');

        if (null !== $id) {
            $post->setId($id);
        }

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return $post;
    }

    protected function createPage(int $id): Page
    {
        $page = new Page();
        $page->setTitle('Test Page');
        $page->setContent('Test content page');
        $page->setId(new DummyObjectId($id));

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        return $page;
    }

    protected function createSearchablePost(): SearchableEntity
    {
        $post = $this->createPost(random_int(100, 300));

        return new SearchableEntity(
            $this->getPrefix().'posts',
            $post,
            $this->get('doctrine')->getManager()->getClassMetadata(Post::class),
            $this->get('serializer')
        );
    }

    protected function createComment(?int $id = null): Comment
    {
        $post = new Post(['title' => 'What a post!']);
        $comment = new Comment();
        $comment->setContent('Comment content');
        $comment->setPost($post);

        if (null !== $id) {
            $comment->setId($id);
        }

        $this->entityManager->persist($post);
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $comment;
    }

    protected function createImage(?int $id = null): Image
    {
        $image = new Image();
        $image->setUrl('https://docs.meilisearch.com/logo.png');

        if (null !== $id) {
            $image->setId($id);
        }

        $this->entityManager->persist($image);
        $this->entityManager->flush();

        return $image;
    }

    protected function createSearchableImage(): SearchableEntity
    {
        $image = $this->createImage(random_int(100, 300));

        return new SearchableEntity(
            $this->getPrefix().'image',
            $image,
            $this->get('doctrine')->getManager()->getClassMetadata(Image::class),
            null
        );
    }

    protected function createTag(array $properties = []): Tag
    {
        $tag = new Tag();
        $tag->setName('Meilisearch Test Tag');

        if (count($properties) > 0) {
            foreach ($properties as $key => $value) {
                $method = 'set'.ucfirst($key);
                $tag->$method($value);
            }
        }

        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        return $tag;
    }

    protected function createLink(array $properties = []): Link
    {
        $link = new Link();
        $link->setName('Meilisearch Test Link');

        if (count($properties) > 0) {
            foreach ($properties as $key => $value) {
                $method = 'set'.ucfirst($key);
                $link->$method($value);
            }
        }

        $this->entityManager->persist($link);
        $this->entityManager->flush();

        return $link;
    }

    protected function getPrefix(): string
    {
        return $this->searchService->getConfiguration()->get('prefix');
    }

    protected function get(string $id): ?object
    {
        return self::getContainer()->get($id);
    }

    protected function getFileName(string $indexName, string $type): string
    {
        return sprintf('%s/%s.json', $indexName, $type);
    }

    protected function waitForAllTasks(): void
    {
        $firstTask = $this->client->getTasks()->getResults()[0];
        $this->client->waitForTask($firstTask['uid']);
    }

    private function cleanUp(): void
    {
        (new Collection($this->searchService->getConfiguration()->get('indices')))
                ->each(function ($item): bool {
                    $this->cleanupIndex($this->getPrefix().$item['name']);

                    return true;
                });

        $this->cleanupIndex($this->getPrefix().'indexA');
        $this->cleanupIndex($this->getPrefix().'indexB');
    }

    private function cleanupIndex(string $indexName): void
    {
        try {
            $this->searchService->deleteByIndexName($indexName);
        } catch (ApiException $e) {
            // Don't assert undefined indexes.
            // Just plainly delete all existing indexes to get a clean state.
        }
    }
}
