<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration;

use Doctrine\Persistence\Proxy;
use Meilisearch\Bundle\Exception\EntityNotFoundInObjectID;
use Meilisearch\Bundle\Exception\InvalidEntityForAggregator;
use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Bundle\Tests\Entity\ContentAggregator;
use Meilisearch\Bundle\Tests\Entity\EmptyAggregator;
use Meilisearch\Bundle\Tests\Entity\Post;
use Symfony\Component\Serializer\Serializer;

class AggregatorTest extends BaseKernelTestCase
{
    public function testGetEntities(): void
    {
        $this->assertEquals([], EmptyAggregator::getEntities());
    }

    public function testGetEntityClassFromObjectID(): void
    {
        $this->expectException(EntityNotFoundInObjectID::class);
        EmptyAggregator::getEntityClassFromObjectID('test');
    }

    public function testConstructor(): void
    {
        $this->expectException(InvalidEntityForAggregator::class);
        $post = new Post();
        new ContentAggregator($post, ['objectId', 'url']);
    }

    public function testAggregatorProxyClass(): void
    {
        $this->createPost();
        $this->entityManager->clear();

        $proxy = $this->entityManager->getReference(Post::class, 1);
        $this->assertInstanceOf(Proxy::class, $proxy);
        $contentAggregator = new ContentAggregator($proxy, ['objectId']);

        /** @var Serializer $serializer */
        $serializer = $this->get('serializer');

        $serializedData = $contentAggregator->normalize($serializer);
        $this->assertNotEmpty($serializedData);
        $this->assertEquals('objectId', $serializedData['objectID']);
    }
}
