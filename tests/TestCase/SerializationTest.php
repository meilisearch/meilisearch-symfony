<?php

namespace MeiliSearch\Bundle\Test\TestCase;

use DateTime;
use MeiliSearch\Bundle\Searchable;
use MeiliSearch\Bundle\SearchableEntity;
use MeiliSearch\Bundle\Test\BaseTest;
use MeiliSearch\Bundle\Test\Entity\Comment;
use MeiliSearch\Bundle\Test\Entity\Post;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Class SerializationTest
 *
 * @package MeiliSearch\Bundle\Test\TestCase
 */
class SerializationTest extends BaseTest
{

    /**
     * @throws ExceptionInterface
     */
    public function testSimpleEntityToSearchableArray()
    {
        $datetime       = new DateTime();
        $dateSerializer = new Serializer([new DateTimeNormalizer()]);
        // This way we can test that DateTime's are serialized with DateTimeNormalizer
        // And not the default ObjectNormalizer
        $serializedDateTime = $dateSerializer->normalize($datetime, Searchable::NORMALIZATION_FORMAT);

        $post = new Post(
            [
                'id'          => 12,
                'title'       => 'a simple post',
                'content'     => 'some text',
                'publishedAt' => $datetime,
            ]
        );
        $post->addComment(
            new Comment(
                [
                    'content'     => 'a great comment',
                    'publishedAt' => $datetime,
                    'post'        => $post,
                ]
            )
        );
        $postMeta = $this->get('doctrine')->getManager()->getClassMetadata(Post::class);

        $searchablePost = new SearchableEntity(
            'posts',
            $post,
            $postMeta,
            $this->get('serializer')
        );

        $expected = [
            'id'          => 12,
            'title'       => 'a simple post',
            'content'     => 'some text',
            'publishedAt' => $serializedDateTime,
            'comments'    => [
                [
                    'content'    => 'a great comment',
                    'post_title' => 'a simple post',
                ],
            ],
        ];

        $this->assertEquals($expected, $searchablePost->getSearchableArray());
    }
}
