<?php

namespace MeiliSearch\Bundle\Test\TestCase;

use Exception;
use MeiliSearch\Bundle\Engine;
use MeiliSearch\Bundle\Test\BaseTest;
use MeiliSearch\Exceptions\HTTPRequestException;

/**
 * Class EngineTest
 *
 * @package MeiliSearch\Bundle\Test\TestCase
 */
class EngineTest extends BaseTest
{

    protected $engine;

    public function setUp(): void
    {
        parent::setUp();

        /* @var Engine */
        $this->engine = new Engine($this->get('search.client'));
    }

    public function testIndexingEmptyEntity()
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
            $this->engine->search('query', $searchableImage->getIndexName(), []);
        } catch (Exception $e) {
            $this->assertInstanceOf(HTTPRequestException::class, $e);
        }
    }
}
