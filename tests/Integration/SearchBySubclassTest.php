<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration;

use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Bundle\Tests\Entity\Article;
use Meilisearch\Bundle\Tests\Entity\ContentItem;
use Meilisearch\Bundle\Tests\Entity\Podcast;

final class SearchBySubclassTest extends BaseKernelTestCase
{
    public function testSearchingBySubclassReturnsTheEntitiesOfItsConfiguredIndex(): void
    {
        $this->entityManager->persist(new Article('A searchable article'));
        $this->entityManager->persist(new Podcast('A searchable podcast'));
        $this->entityManager->flush();

        $this->waitForAllTasks();

        // Only the abstract parent is configured as an index, so both the parent and either
        // subclass have to resolve to it.
        foreach ([ContentItem::class, Article::class, Podcast::class] as $className) {
            $results = $this->searchManager->search($className, 'searchable');

            self::assertCount(2, $results->getHits(), $className.' resolved to the wrong index');
        }
    }
}
