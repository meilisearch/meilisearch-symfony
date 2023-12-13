<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit;

use Meilisearch\Bundle\Searchable;
use Meilisearch\Bundle\SearchableEntity;
use Meilisearch\Bundle\Tests\Entity\Comment;
use Meilisearch\Bundle\Tests\Entity\Post;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Class SerializationTest.
 */
class SerializationTest extends KernelTestCase
{
    public function testSimpleEntityToSearchableArray(): void
    {
        $datetime = new \DateTime();

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
            ['normalizationGroups' => [Searchable::NORMALIZATION_GROUP]]
        );

        $expected = [
            'id' => 12,
            'title' => 'a simple post',
            'content' => 'some text',
            'publishedAt' => (int) $datetime->format('U'),
            'comments' => [
                [
                    'id' => null,
                    'content' => 'a great comment',
                    'publishedAt' => (int) $datetime->format('U'),
                ],
            ],
        ];

        $this->assertEquals($expected, $searchablePost->getSearchableArray());
    }
}
