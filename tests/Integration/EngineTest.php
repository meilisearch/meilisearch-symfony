<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration;

use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\SearchableEntity;
use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Bundle\Tests\Entity\Image;
use Meilisearch\Bundle\Tests\Entity\Post;
use Meilisearch\Exceptions\ApiException;

final class EngineTest extends BaseKernelTestCase
{
    protected Engine $engine;

    public function setUp(): void
    {
        parent::setUp();

        $this->engine = new Engine($this->get('meilisearch.client'));
    }

    /**
     * @throws ApiException
     */
    public function testIndexingEmptyEntity(): void
    {
        $image = new Image();

        $this->entityManager->persist($image);
        $this->entityManager->flush();

        $searchableImage = new SearchableEntity(
            $this->getPrefix().'image',
            $image,
            $this->get('doctrine')->getManager()->getClassMetadata(Image::class),
            null
        );

        // Index
        $result = $this->engine->index($searchableImage);
        $this->assertEmpty($result);

        // Remove
        $result = $this->engine->remove($searchableImage);
        $this->assertArrayHasKey('sf_phpunit__image', $result);

        // Update
        $result = $this->engine->index($searchableImage);
        $this->assertEmpty($result);

        // Search
        try {
            $this->engine->search('query', $searchableImage->getIndexUid(), []);
        } catch (\Exception $e) {
            $this->assertInstanceOf(ApiException::class, $e);
        }
    }

    public function testRemovingMultipleEntity(): void
    {
        $metadata = $this->get('doctrine')->getManager()->getClassMetadata(Post::class);
        $serializer = $this->get('serializer');

        $this->entityManager->persist($post1 = new Post());
        $this->entityManager->persist($post2 = new Post());

        $this->entityManager->flush();

        $postSearchable1 = new SearchableEntity($this->getPrefix().'posts', $post1, $metadata, $serializer);
        $postSearchable2 = new SearchableEntity($this->getPrefix().'posts', $post2, $metadata, $serializer);

        $result = $this->engine->remove([$postSearchable1, $postSearchable2]);

        $this->assertArrayHasKey('sf_phpunit__posts', $result);
        $this->assertCount(2, $result['sf_phpunit__posts']);

        $this->waitForAllTasks();

        foreach ([$postSearchable1, $postSearchable2] as $post) {
            $searchResult = $this->engine->search('', $post->getIndexUid(), []);

            $this->assertArrayHasKey('hits', $searchResult);
            $this->assertIsArray($searchResult['hits']);
            $this->assertEmpty($searchResult['hits']);
        }
    }
}
