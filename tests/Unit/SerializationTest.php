<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit;

use Meilisearch\Bundle\Searchable;
use Meilisearch\Bundle\SearchableEntity;
use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Bundle\Tests\Entity\Comment;
use Meilisearch\Bundle\Tests\Entity\Post;

final class SerializationTest extends BaseKernelTestCase
{
    public function testSimpleEntityToSearchableArray(): void
    {
        $post = new Post('a simple post', 'some text', $datetime = new \DateTimeImmutable('@1728994403'));
        $idReflection = (new \ReflectionObject($post))->getProperty('id');
        $idReflection->setAccessible(true);
        $idReflection->setValue($post, 12);

        $comment = new Comment($post, 'a great comment', $datetime);
        $post->addComment($comment);

        $searchablePost = new SearchableEntity(
            'posts',
            $post,
            self::getContainer()->get('doctrine')->getManager()->getClassMetadata(Post::class),
            self::getContainer()->get('serializer'),
            ['normalizationGroups' => [Searchable::NORMALIZATION_GROUP]]
        );

        $this->assertSame([
            'id' => 12,
            'title' => 'a simple post',
            'content' => 'some text',
            'publishedAt' => 1728994403,
            'comments' => [
                [
                    'id' => null,
                    'content' => 'a great comment',
                    'publishedAt' => 1728994403,
                ],
            ],
        ], $searchablePost->getSearchableArray());
    }
}
