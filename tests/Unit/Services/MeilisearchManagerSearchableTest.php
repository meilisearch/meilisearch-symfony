<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit\Services;

use Meilisearch\Bundle\DataProvider\DataProviderRegistryInterface;
use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\Exception\NotSearchableException;
use Meilisearch\Bundle\Services\MeilisearchManager;
use Meilisearch\Bundle\Tests\Entity\Article;
use Meilisearch\Bundle\Tests\Entity\ContentItem;
use Meilisearch\Bundle\Tests\Entity\Podcast;
use Meilisearch\Bundle\Tests\Entity\Post;
use Meilisearch\Client;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class MeilisearchManagerSearchableTest extends TestCase
{
    public function testAConfiguredClassIsSearchable(): void
    {
        $manager = $this->createManager([$this->index('content', ContentItem::class)]);

        self::assertTrue($manager->isSearchable(ContentItem::class));
        self::assertSame('app_content', $manager->searchableAs(ContentItem::class));
    }

    public function testASubclassOfAConfiguredClassIsSearchable(): void
    {
        $manager = $this->createManager([$this->index('content', ContentItem::class)]);

        self::assertTrue($manager->isSearchable(Article::class));
        self::assertSame('app_content', $manager->searchableAs(Article::class));
    }

    public function testAClassConfiguredInItsOwnRightKeepsItsOwnIndex(): void
    {
        $manager = $this->createManager([
            $this->index('content', ContentItem::class),
            $this->index('articles', Article::class),
        ]);

        self::assertSame('app_articles', $manager->searchableAs(Article::class));
        self::assertSame('app_content', $manager->searchableAs(Podcast::class));
    }

    public function testTheNearestConfiguredAncestorWins(): void
    {
        // SpecialArticle extends Article extends ContentItem, and both ancestors are configured.
        foreach ([
            [$this->index('content', ContentItem::class), $this->index('articles', Article::class)],
            [$this->index('articles', Article::class), $this->index('content', ContentItem::class)],
        ] as $indices) {
            $manager = $this->createManager($indices);

            self::assertSame(
                'app_articles',
                $manager->searchableAs(SpecialArticle::class),
                'the nearest ancestor must win whatever order the indices are configured in',
            );
        }
    }

    public function testAnUnrelatedClassIsNotSearchable(): void
    {
        $manager = $this->createManager([$this->index('content', ContentItem::class)]);

        self::assertFalse($manager->isSearchable(Post::class));

        $this->expectException(NotSearchableException::class);

        $manager->searchableAs(Post::class);
    }

    /**
     * @param class-string $class
     *
     * @return array<string, mixed>
     */
    private function index(string $name, string $class): array
    {
        return [
            'name' => $name,
            'prefixed_name' => 'app_'.$name,
            'class' => $class,
            'enable_serializer_groups' => false,
            'serializer_groups' => [],
            'primary_key' => 'id',
        ];
    }

    /**
     * @param list<array<string, mixed>> $indices
     */
    private function createManager(array $indices): MeilisearchManager
    {
        return new MeilisearchManager(
            $this->createStub(NormalizerInterface::class),
            new Engine($this->createStub(Client::class)),
            $this->createStub(PropertyAccessorInterface::class),
            $this->createStub(DataProviderRegistryInterface::class),
            [
                'prefix' => 'app_',
                'batchSize' => 100,
                'nbResults' => 20,
                'indices' => $indices,
            ],
        );
    }
}

/**
 * Three levels of inheritance, which no test entity provides.
 */
final class SpecialArticle extends Article
{
}
