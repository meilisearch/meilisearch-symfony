<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Debug;

use Doctrine\Persistence\ObjectManager;
use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\SearchService;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Antoine Makdessi <amakdessi@me.com>
 */
final class TraceableMeilisearchService implements SearchService
{
    private SearchService $searchService;
    private Stopwatch $stopwatch;
    private array $data = [];

    public function __construct(SearchService $searchService, Stopwatch $stopwatch)
    {
        $this->searchService = $searchService;
        $this->stopwatch = $stopwatch;
    }

    public function index(ObjectManager $objectManager, $searchable): array
    {
        return $this->innerSearchService(__FUNCTION__, \func_get_args());
    }

    public function remove(ObjectManager $objectManager, $searchable): array
    {
        return $this->innerSearchService(__FUNCTION__, \func_get_args());
    }

    public function clear(string $className): array
    {
        return $this->innerSearchService(__FUNCTION__, \func_get_args());
    }

    public function deleteByIndexName(string $indexName): ?array
    {
        return $this->innerSearchService(__FUNCTION__, \func_get_args());
    }

    public function delete(string $className): ?array
    {
        return $this->innerSearchService(__FUNCTION__, \func_get_args());
    }

    public function search(ObjectManager $objectManager, string $className, string $query = '', array $searchParams = []): array
    {
        return $this->innerSearchService(__FUNCTION__, \func_get_args());
    }

    public function rawSearch(string $className, string $query = '', array $searchParams = []): array
    {
        return $this->innerSearchService(__FUNCTION__, \func_get_args());
    }

    public function count(string $className, string $query = '', array $searchParams = []): int
    {
        return $this->innerSearchService(__FUNCTION__, \func_get_args());
    }

    public function isSearchable($className): bool
    {
        return $this->searchService->isSearchable($className);
    }

    public function getSearchable(): array
    {
        return $this->searchService->getSearchable();
    }

    public function getConfiguration(): Collection
    {
        return $this->searchService->getConfiguration();
    }

    public function searchableAs(string $className): string
    {
        return $this->searchService->searchableAs($className);
    }

    /** @internal used in the DataCollector class */
    public function getData(): array
    {
        return $this->data;
    }

    private function innerSearchService(string $function, array $args): mixed
    {
        $this->stopwatch->start($function);

        $result = $this->searchService->{$function}(...$args);

        $event = $this->stopwatch->stop($function);

        $this->data[$function] = [
            '_params' => $args,
            '_results' => $result,
            '_duration' => $event->getDuration(),
            '_memory' => $event->getMemory(),
        ];

        return $result;
    }
}
