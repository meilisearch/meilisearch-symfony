<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration;

use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Exceptions\ApiException;

/**
 * Class EngineTest.
 */
class EngineTest extends BaseKernelTestCase
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
        $searchableImage = $this->createSearchableImage();

        // Index
        $result = $this->engine->index($searchableImage);
        $this->assertEmpty($result);

        // Remove
        $result = $this->engine->remove($searchableImage);
        $this->assertEmpty($result);

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
        $post1 = $this->createSearchablePost();
        $post2 = $this->createSearchablePost();

        $result = $this->engine->remove([$post1, $post2]);

        $this->assertArrayHasKey('sf_phpunit__posts', $result);
        $this->assertCount(2, $result['sf_phpunit__posts']);

        $this->waitForAllTasks();

        foreach ([$post1, $post2] as $post) {
            $searchResult = $this->engine->search('', $post->getIndexUid(), []);

            $this->assertArrayHasKey('hits', $searchResult);
            $this->assertIsArray($searchResult['hits']);
            $this->assertEmpty($searchResult['hits']);
        }
    }
}
