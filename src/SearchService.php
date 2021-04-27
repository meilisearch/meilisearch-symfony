<?php

namespace MeiliSearch\Bundle;

use Doctrine\Persistence\ObjectManager;

/**
 * Interface SearchService.
 *
 * @package MeiliSearch
 */
interface SearchService
{
    /**
     * @param string|object $className
     */
    public function isSearchable($className): bool;

    public function getSearchable(): array;
    public function getConfiguration(): array;

    /**
     * Get the index name for the given `$className`.
     */
    public function searchableAs(string $className): string;

    public function index(ObjectManager $objectManager, $searchable): array;
    public function remove(ObjectManager $objectManager, $searchable): array;
    public function clear(string $className): array;
    public function delete(string $className): ?array;
    public function search(
        ObjectManager $objectManager,
        string $className,
        string $query = '',
        array $requestOptions = []
    ): array;

    /**
     * Get the raw search result.
     *
     * @see https://docs.meilisearch.com/reference/api/search.html#response
     */
    public function rawSearch(
        string $className,
        string $query = '',
        array $searchParams = []
    ): array;

    public function count(string $className, string $query = '', array $requestOptions = []): int;
}
