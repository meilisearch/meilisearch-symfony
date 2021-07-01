<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Test\TestCase;

use MeiliSearch\Bundle\Searchable;
use MeiliSearch\Bundle\SearchableEntity;
use MeiliSearch\Bundle\Test\BaseTest;
use MeiliSearch\Bundle\Test\Entity\Comment;
use MeiliSearch\Bundle\Test\Entity\Post;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Class SerializationTest.
 */
class SerializationTest extends BaseTest
{
    /**
     * @throws ExceptionInterface
     */
    public function testSimpleEntityToSearchableArray()
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

        $postMeta = $this->get('doctrine')->getManager()->getClassMetadata(Post::class);

        $searchablePost = new SearchableEntity(
            'posts',
            $post,
            $postMeta,
            $this->get('serializer'),
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
