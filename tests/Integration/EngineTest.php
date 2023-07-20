<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration;

use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\SearchableObject;
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

        $searchableImage = new SearchableObject(
            $this->getPrefix().'image',
            'objectID',
            $image,
            $image->getId(),
            $this->get('serializer'),
            ['groups' => ['force_empty']],
        );

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
        $this->entityManager->persist($post1 = new Post());
        $this->entityManager->persist($post2 = new Post());

        $this->entityManager->flush();

        $serializer = $this->get('serializer');
        $postSearchable1 = new SearchableObject($this->getPrefix().'posts', 'objectID', $post1, $post1->getId(), $serializer);
        $postSearchable2 = new SearchableObject($this->getPrefix().'posts', 'objectID', $post2, $post2->getId(), $serializer);

        $result = $this->engine->remove([$postSearchable1, $postSearchable2]);

        $this->assertArrayHasKey('sf_phpunit__posts', $result);
        $this->assertCount(2, $result['sf_phpunit__posts']);

        $this->waitForAllTasks();

        foreach ([$postSearchable1, $postSearchable2] as $post) {
            $searchResult = $this->engine->search('', $post->getIndexUid(), []);

            $this->assertEmpty($searchResult['hits']);
        }
    }
}
