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
     *
     * @return bool
     */
    public function isSearchable($className): bool;

    /**
     * @return array
     */
    public function getSearchable(): array;

    /**
     * @return array
     */
    public function getConfiguration(): array;

    /**
     * Get the index name for the given `$className`.
     *
     * @param string $className
     *
     * @return string
     */
    public function searchableAs(string $className): string;

    /**
     * @param ObjectManager $objectManager
     * @param               $searchable
     *
     * @return array
     */
    public function index(ObjectManager $objectManager, $searchable): array;

    /**
     * @param ObjectManager $objectManager
     * @param               $searchable
     *
     * @return array
     */
    public function remove(ObjectManager $objectManager, $searchable): array;

    /**
     * @param string $className
     *
     * @return array
     */
    public function clear(string $className): array;

    /**
     * @param string $className
     *
     * @return array|null
     */
    public function delete(string $className): ?array;

    /**
     * @param ObjectManager $objectManager
     * @param string        $className
     * @param string        $query
     * @param array         $requestOptions
     *
     * @return array
     */
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
     *
     * @param string $className
     * @param string $query
     * @param array  $searchParams
     *
     * @return array
     */
    public function rawSearch(
        string $className,
        string $query = '',
        array $searchParams = []
    ): array;

    /**
     * @param string $className
     * @param string $query
     * @param array  $requestOptions
     *
     * @return int
     */
    public function count(string $className, string $query = '', array $requestOptions = []): int;
}
