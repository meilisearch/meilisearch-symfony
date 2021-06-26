<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Test\Integration;

use MeiliSearch\Bundle\Exception\EntityNotFoundInObjectID;
use MeiliSearch\Bundle\Exception\InvalidEntityForAggregator;
use MeiliSearch\Bundle\Test\BaseKernelTestCase;
use MeiliSearch\Bundle\Test\Entity\ContentAggregator;
use MeiliSearch\Bundle\Test\Entity\EmptyAggregator;
use MeiliSearch\Bundle\Test\Entity\Post;
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

        $postMetadata = $this->entityManager->getClassMetadata(Post::class);
        $this->entityManager->getProxyFactory()->generateProxyClasses([$postMetadata]);

        $proxy = $this->entityManager->getProxyFactory()->getProxy($postMetadata->getName(), ['id' => 1]);
        $contentAggregator = new ContentAggregator($proxy, ['objectId']);

        /** @var Serializer $serializer */
        $serializer = $this->get('serializer');

        $serializedData = $contentAggregator->normalize($serializer);
        $this->assertNotEmpty($serializedData);
        $this->assertEquals('objectId', $serializedData['objectID']);
    }
}
