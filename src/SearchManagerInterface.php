<?php

declare(strict_types=1);

namespace Meilisearch\Bundle;

use Meilisearch\Bundle\Exception\NotSearchableException;

/**
 * @phpstan-import-type IndexDeletionTask from Engine
 * @phpstan-import-type DocumentDeletionTask from Engine
 * @phpstan-import-type DocumentAdditionOrUpdateTask from Engine
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
     * @return list<array<non-empty-string, DocumentAdditionOrUpdateTask>>
     *
     * @throws NotSearchableException
     */
    public function index(object|array $searchable): array;

    /**
     * @param object|array<object> $searchable
     *
     * @return array<non-empty-string, list<DocumentDeletionTask>>
     *
     * @throws NotSearchableException
     */
    public function remove(object|array $searchable): array;

    /**
     * @param class-string $className
     *
     * @return DocumentDeletionTask
     *
     * @throws NotSearchableException
     */
    public function clear(string $className): array;

    /**
     * @param non-empty-string $indexName
     *
     * @return IndexDeletionTask
     */
    public function deleteByIndexName(string $indexName): array;

    /**
     * @param class-string $className
     *
     * @return IndexDeletionTask
     *
     * @throws NotSearchableException
     */
    public function delete(string $className): array;

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     * @param array<mixed>    $searchParams
     *
     * @return list<T>
     *
     * @throws NotSearchableException
     */
    public function search(string $className, string $query = '', array $searchParams = []): array;

    /**
     * @param class-string $className
     * @param array<mixed> $searchParams
     *
     * @return SearchResponse
     *
     * @throws NotSearchableException
     */
    public function rawSearch(string $className, string $query = '', array $searchParams = []): array;

    /**
     * @param class-string $className
     * @param array<mixed> $searchParams
     *
     * @throws NotSearchableException
     */
    public function count(string $className, string $query = '', array $searchParams = []): int;
}
