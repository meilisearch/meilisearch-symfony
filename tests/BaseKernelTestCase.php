<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Test;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use MeiliSearch\Bundle\SearchableEntity;
use MeiliSearch\Bundle\SearchService;
use MeiliSearch\Bundle\Test\Entity\Comment;
use MeiliSearch\Bundle\Test\Entity\Image;
use MeiliSearch\Bundle\Test\Entity\Link;
use MeiliSearch\Bundle\Test\Entity\Post;
use MeiliSearch\Bundle\Test\Entity\Tag;
use MeiliSearch\Exceptions\ApiException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class BaseKernelTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;
    protected SearchService $searchService;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::$kernel->getContainer()->get('doctrine.orm.entity_manager');
        $this->searchService = $this->get('search.service');

        $metaData = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->entityManager);
        $tool->dropSchema($metaData);
        $tool->createSchema($metaData);

        $this->cleanUp();
    }

    /**
     * @param int|string|null $id
     */
    protected function createPost($id = null): Post
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

    /**
     * @param int|string|null $id
     */
    protected function createComment($id = null): Comment
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

    /**
     * @param int|string|null $id
     */
    protected function createImage($id = null): Image
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
        return self::$kernel->getContainer()->get($id);
    }

    protected function getFileName(string $indexName, string $type): string
    {
        return sprintf('%s/%s.json', $indexName, $type);
    }

    private function cleanUp(): void
    {
        collect($this->searchService->getConfiguration()->get('indices'))
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
