<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Test\Unit;

use Meilisearch\Bundle\Searchable;
use Meilisearch\Bundle\SearchableEntity;
use Meilisearch\Bundle\Test\Entity\Comment;
use Meilisearch\Bundle\Test\Entity\Post;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Class SerializationTest.
 */
class SerializationTest extends KernelTestCase
{
    /**
     * @throws ExceptionInterface
     */
    public function testSimpleEntityToSearchableArray(): void
    {
        $datetime = new \DateTime();
        $dateSerializer = new Serializer([new DateTimeNormalizer()]);
        // This way we can test that DateTime's are serialized with DateTimeNormalizer
        // And not the default ObjectNormalizer
        $serializedDateTime = $dateSerializer->normalize($datetime, Searchable::NORMALIZATION_FORMAT);

        $post = new Post(
            [
                'id' => 12,
                'title' => 'a simple post',
                'content' => 'some text',
                'publishedAt' => $datetime,
            ]
        );

        $comment = new Comment();
        $comment->setContent('a great comment');
        $comment->setPost($post);
        $post->addComment($comment);

        $postMeta = static::getContainer()->get('doctrine')->getManager()->getClassMetadata(Post::class);

        $searchablePost = new SearchableEntity(
            'posts',
            $post,
            $postMeta,
            static::getContainer()->get('serializer'),
            ['useSerializerGroup' => true]
        );

        $expected = [
            'id' => 12,
            'title' => 'a simple post',
            'content' => 'some text',
            'publishedAt' => $serializedDateTime,
            'comments' => [
                [
                    'id' => null,
                    'content' => 'a great comment',
                    'publishedAt' => $serializedDateTime,
                ],
            ],
        ];

        $this->assertEquals($expected, $searchablePost->getSearchableArray());
    }
}
