<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Test\Integration;

use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\Test\BaseKernelTestCase;
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

        $this->engine = new Engine($this->get('search.client'));
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
}
