<?php

declare(strict_types=1);

namespace Meilisearch\Bundle;

use Meilisearch\Bundle\Exception\NotSearchableException;
use Meilisearch\Bundle\Model\SearchResults;
use Meilisearch\Contracts\Task;
use Meilisearch\Exceptions\ExceptionInterface;

/**
 * @phpstan-import-type SearchResponse from Engine
 */
interface SearchManagerInterface
{
    public const RESULT_KEY_HITS = 'hits';

    /**
     * @param object|class-string $className
     */
    public function isSearchable(object|string $className): bool;

    /**
     * @param class-string $className
     *
     * @return non-empty-string
     *
     * @throws NotSearchableException
     */
    public function searchableAs(string $className): string;

    public function getConfiguration(): Collection;

    /**
     * @param object|array<object> $searchable
     *
     * @return list<array<non-empty-string, Task>>
     *
     * @throws NotSearchableException
     * @throws ExceptionInterface
     */
    public function index(object|array $searchable): array;

    /**
     * @param object|array<object> $searchable
     *
     * @return array<non-empty-string, list<Task>>
     *
     * @throws NotSearchableException
     * @throws ExceptionInterface
     */
    public function remove(object|array $searchable): array;

    /**
     * @param class-string $className
     *
     * @throws NotSearchableException
     * @throws ExceptionInterface
     */
    public function clear(string $className): Task;

    /**
     * @param non-empty-string $indexName
     *
     * @throws ExceptionInterface
     */
    public function deleteByIndexName(string $indexName): Task;

    /**
     * @param class-string $className
     *
     * @throws NotSearchableException
     * @throws ExceptionInterface
     */
    public function delete(string $className): Task;

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     * @param array<mixed>    $searchParams
     *
     * @return SearchResults<T>
     *
     * @throws NotSearchableException
     * @throws ExceptionInterface
     */
    public function search(string $className, string $query = '', array $searchParams = []): SearchResults;

    /**
     * @param class-string $className
     * @param array<mixed> $searchParams
     *
     * @return SearchResponse
     *
     * @throws NotSearchableException
     * @throws ExceptionInterface
     */
    public function rawSearch(string $className, string $query = '', array $searchParams = []): array;

    /**
     * @param class-string $className
     * @param array<mixed> $searchParams
     *
     * @throws NotSearchableException
     * @throws ExceptionInterface
     */
    public function count(string $className, string $query = '', array $searchParams = []): int;
}
