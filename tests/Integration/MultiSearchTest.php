<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration;

use Meilisearch\Bundle\Contracts\SearchQuery;
use Meilisearch\Bundle\Model\SearchResults;
use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Bundle\Tests\Entity\Movie;
use Meilisearch\Bundle\Tests\Entity\Post;

final class MultiSearchTest extends BaseKernelTestCase
{
    public function testEachQueryComesBackHydratedWithItsOwnEntities(): void
    {
        $this->entityManager->persist(new Post('Searchable post', 'Content'));
        $this->entityManager->persist(new Movie('Searchable movie', 'comedy'));
        $this->entityManager->flush();

        $this->waitForAllTasks();

        $results = $this->searchManager->multiSearch([
            // A null filter is what a single search already accepts, so the engine has to take
            // it here too rather than the client rejecting it on the way out.
            new SearchQuery(Post::class, 'searchable', ['filter' => null]),
            new SearchQuery(Movie::class, 'searchable'),
        ]);

        self::assertCount(2, $results);
        self::assertContainsOnlyInstancesOf(SearchResults::class, $results);

        self::assertCount(1, $results[0]->getHits());
        self::assertContainsOnlyInstancesOf(Post::class, $results[0]->getHits());

        self::assertCount(1, $results[1]->getHits());
        self::assertContainsOnlyInstancesOf(Movie::class, $results[1]->getHits());
    }
}
