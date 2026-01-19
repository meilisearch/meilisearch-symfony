<?php

declare(strict_types=1);

namespace Meilisearch\Bundle;

use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated Since 0.16, use `Meilisearch\Bundle\SearchManagerInterface` instead.
 */
interface SearchService
{
    public const RESULT_KEY_HITS = 'hits';
    public const RESULT_KEY_OBJECTID = 'objectID';

    /**
     * @param class-string|object $className
     */
    public function isSearchable($className): bool;

    /**
     * @return list<class-string>
     */
    public function getSearchable(): array;

    public function getConfiguration(): Collection;

    /**
     * Get the index name for the given `$className`.
     *
     * @param class-string $className
     */
    public function searchableAs(string $className): string;

    public function index(ObjectManager $objectManager, $searchable): array;

    public function remove(ObjectManager $objectManager, $searchable): array;

    /**
     * @param class-string $className
     */
    public function clear(string $className): array;

    /**
     * @param class-string $className
     */
    public function delete(string $className): ?array;

    public function deleteByIndexName(string $indexName): ?array;

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return list<T>
     */
    public function search(
        ObjectManager $objectManager,
        string $className,
        string $query = '',
        array $searchParams = [],
    ): array;

    /**
     * Get the raw search result.
     *
     * @see https://docs.meilisearch.com/reference/api/search.html#response
     *
     * @param class-string $className
     */
    public function rawSearch(
        string $className,
        string $query = '',
        array $searchParams = [],
    ): array;

    /**
     * @param class-string $className
     *
     * @return int<0, max>
     */
    public function count(string $className, string $query = '', array $searchParams = []): int;
}
