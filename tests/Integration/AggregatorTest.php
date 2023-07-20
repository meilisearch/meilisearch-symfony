<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration;

use Doctrine\ORM\Mapping\LegacyReflectionFields;
use Doctrine\Persistence\Proxy;
use Meilisearch\Bundle\Exception\EntityNotFoundInObjectID;
use Meilisearch\Bundle\Exception\InvalidEntityForAggregator;
use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Bundle\Tests\Entity\ContentAggregator;
use Meilisearch\Bundle\Tests\Entity\EmptyAggregator;
use Meilisearch\Bundle\Tests\Entity\Post;
use Symfony\Component\Serializer\Serializer;

final class AggregatorTest extends BaseKernelTestCase
{
    public function testGetEntities(): void
    {
        $this->assertEquals([], EmptyAggregator::getEntities());
    }

    public function testGetEntityClassFromObjectID(): void
    {
        self::assertSame(Post::class, ContentAggregator::getEntityClassFromObjectID(Post::class.'::123'));
    }

    public function testGetEntityClassFromObjectIDWithUnknownEntityThrows(): void
    {
        $this->expectException(EntityNotFoundInObjectID::class);

        EmptyAggregator::getEntityClassFromObjectID('test');
    }

    public function testConstructorThrowsWithMoreThanOnePrimaryKey(): void
    {
        $post = new Post();

        $this->expectException(InvalidEntityForAggregator::class);

        new ContentAggregator($post, ['objectId', 'url'], 'objectId');
    }

    public function testAggregatorProxyClass(): void
    {
        if (class_exists(LegacyReflectionFields::class)) {
            $this->markTestSkipped('Skipping, because proxies are not wrapped anymore with lazy native objects.');
        }

        $this->entityManager->persist($post = new Post());
        $this->entityManager->flush();
        $postId = $post->getId();
        $this->entityManager->clear();

        $proxy = $this->entityManager->getReference(Post::class, $postId);
        $this->assertInstanceOf(Proxy::class, $proxy);
        $contentAggregator = new ContentAggregator($proxy, ['objectId'], 'objectId');

        /** @var Serializer $serializer */
        $serializer = $this->get('serializer');

        $serializedData = $contentAggregator->normalize($serializer);

        $this->assertNotEmpty($serializedData);
        $this->assertEquals('objectId', $serializedData['objectId']);
    }

    public function testAggregatorNormalization(): void
    {
        $this->entityManager->persist($post = new Post());
        $this->entityManager->flush();

        $contentAggregator = new ContentAggregator($post, [$post->getId()]);

        /** @var Serializer $serializer */
        $serializer = $this->get('serializer');

        $serializedData = $contentAggregator->normalize($serializer);

        $this->assertNotEmpty($serializedData);
        $this->assertSame((string) $post->getId(), $serializedData['objectID']);
        $this->assertSame($post->getId(), $serializedData['id']);
    }

    public function testAggregatorCustomPrimaryKey(): void
    {
        $this->entityManager->persist($post = new Post());
        $this->entityManager->flush();

        $contentAggregator = new ContentAggregator($post, [$post->getId()], 'id');

        /** @var Serializer $serializer */
        $serializer = $this->get('serializer');

        $serializedData = $contentAggregator->normalize($serializer);

        $this->assertNotEmpty($serializedData);
        $this->assertSame($post->getId(), $serializedData['id']);
    }
}
