<?php

namespace MeiliSearch\Bundle\Test\TestCase;

use Doctrine\ORM\EntityManagerInterface;
use MeiliSearch\Bundle\Exception\EntityNotFoundInObjectID;
use MeiliSearch\Bundle\Exception\InvalidEntityForAggregator;
use MeiliSearch\Bundle\Test\BaseTest;
use MeiliSearch\Bundle\Test\Entity\ContentAggregator;
use MeiliSearch\Bundle\Test\Entity\EmptyAggregator;
use MeiliSearch\Bundle\Test\Entity\Post;
use Symfony\Bundle\FrameworkBundle\Console\Application;

/**
 * Class AggregatorTest
 *
 * @package MeiliSearch\Bundle\Test\TestCase
 */
class AggregatorTest extends BaseTest
{

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    public function setUp(): void
    {
        parent::setUp();

        $application = new Application(self::$kernel);
        $this->refreshDb($application);

        $this->entityManager = $this->get('doctrine')->getManager();
    }

    public function testGetEntities()
    {
        $entities = EmptyAggregator::getEntities();

        $this->assertEquals([], $entities);
    }

    public function testGetEntityClassFromObjectID()
    {
        $this->expectException(EntityNotFoundInObjectID::class);
        EmptyAggregator::getEntityClassFromObjectID('test');
    }

    public function testConstructor()
    {
        $this->expectException(InvalidEntityForAggregator::class);
        $post = new Post();
        new ContentAggregator($post, ['objectId', 'url']);
    }

    public function testAggregatorProxyClass()
    {
        $post = new Post(
            [
                'id'      => 1,
                'title'   => 'Test',
                'content' => 'Test content',
            ]
        );
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $postMetadata = $this->entityManager->getClassMetadata(Post::class);
        $this->entityManager->getProxyFactory()->generateProxyClasses([$postMetadata], null);

        $proxy             = $this->entityManager->getProxyFactory()->getProxy($postMetadata->getName(), ['id' => 1]);
        $contentAggregator = new ContentAggregator($proxy, ['objectId']);

        $serializer = $this->get('serializer');

        $serializedData = $contentAggregator->normalize($serializer);
        $this->assertNotEmpty($serializedData);
        $this->assertEquals('objectId', $serializedData['objectID']);
    }
}
