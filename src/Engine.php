<?php

declare(strict_types=1);

namespace Meilisearch\Bundle;

use Meilisearch\Client;
use Meilisearch\Contracts\Http;
use Meilisearch\Contracts\Task;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Exceptions\TimeOutException;

use function Meilisearch\partial;

/**
 * @phpstan-type SearchResponse array{
 *     hits: array<int, mixed>,
 *     query: string,
 *     processingTimeMs: int,
 *     limit: int,
 *     offset: int,
 *     estimatedTotalHits: int,
 *     requestUid: non-empty-string,
 *     nbHits: int
 * }
 */
final class Engine
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Add new objects to an index.
     * This method allows you to create records on your index by sending one or more objects.
     * Each object contains a set of attributes and values, which represents a full record on an index.
     *
     * @param SearchableObject|array<SearchableObject> $searchableObjects
     *
     * @return array<non-empty-string, Task>
     *
     * @throws ApiException
     */
    public function index(SearchableObject|array $searchableObjects): array
    {
        if ($searchableObjects instanceof SearchableObject) {
            $searchableObjects = [$searchableObjects];
        }

        $data = [];

        foreach ($searchableObjects as $object) {
            $searchableArray = $object->getSearchableArray();

            if ([] === $searchableArray) {
                continue;
            }

            $indexUid = $object->getIndexUid();

            $data[$indexUid] ??= ['primaryKey' => $object->getPrimaryKey(), 'documents' => []];
            $data[$indexUid]['documents'][] = $searchableArray + [$object->getPrimaryKey() => $this->normalizeId($object->getIdentifier())];
        }

        $result = [];
        foreach ($data as $indexUid => $batch) {
            $result[$indexUid] = $this->wrapTask(
                $this->client
                    ->index($indexUid)
                    ->addDocuments($batch['documents'], $batch['primaryKey'])
            );
        }

        return $result;
    }

    /**
     * Remove objects from an index using their object UIDs.
     * This method enables you to remove one or more objects from an index.
     *
     * @param SearchableObject|array<SearchableObject> $searchableObjects
     *
     * @return array<non-empty-string, list<Task>>
     */
    public function remove(SearchableObject|array $searchableObjects): array
    {
        if ($searchableObjects instanceof SearchableObject) {
            $searchableObjects = [$searchableObjects];
        }

        $data = [];

        foreach ($searchableObjects as $object) {
            $indexUid = $object->getIndexUid();

            $data[$indexUid] ??= [];
            $data[$indexUid][] = $this->normalizeId($object->getIdentifier());
        }

        $result = [];
        foreach ($data as $indexUid => $objects) {
            $result[$indexUid] = [];
            foreach ($objects as $object) {
                $result[$indexUid][] = $this->wrapTask(
                    $this->client
                        ->index($indexUid)
                        ->deleteDocument($object)
                );
            }
        }

        return $result;
    }

    /**
     * Clear the records of an index.
     * This method enables you to delete an index’s contents (records).
     * Will fail if the index does not exist.
     *
     * @throws ApiException
     */
    public function clear(string $indexUid): Task
    {
        return $this->wrapTask($this->client->index($indexUid)->deleteAllDocuments());
    }

    /**
     * Delete an index and its content.
     */
    public function delete(string $indexUid): Task
    {
        return $this->wrapTask($this->client->deleteIndex($indexUid));
    }

    /**
     * Method used for querying an index.
     *
     * @param array<mixed> $searchParams
     *
     * @return SearchResponse
     */
    public function search(string $query, string $indexUid, array $searchParams): array
    {
        return $this->client->index($indexUid)->rawSearch('' !== $query ? $query : null, $searchParams);
    }

    /**
     * Search the index and returns the number of results.
     *
     * @param array<mixed> $searchParams
     */
    public function count(string $query, string $indexName, array $searchParams): int
    {
        return $this->client->index($indexName)->search($query, $searchParams)->getHitsCount();
    }

    private function normalizeId(\Stringable|string|int $id): string|int
    {
        if (\is_object($id)) {
            return (string) $id;
        }

        return $id;
    }

    private function wrapTask(Task|array $task): Task
    {
        if ($task instanceof Task) {
            return $task;
        }

        $http = (new \ReflectionObject($this->client))->getProperty('http')->getValue($this->client);

        return Task::fromArray($task, partial(self::waitTask(...), $http));
    }

    /**
     * @internal
     *
     * @throws TimeOutException
     */
    public static function waitTask(Http $http, int $taskUid, int $timeoutInMs, int $intervalInMs): Task
    {
        $timeoutTemp = 0;

        while ($timeoutInMs > $timeoutTemp) {
            $task = Task::fromArray($http->get('/tasks/'.$taskUid), partial(self::waitTask(...), $http));

            if ($task->isFinished()) {
                return $task;
            }

            $timeoutTemp += $intervalInMs;
            usleep(1000 * $intervalInMs);
        }

        throw new TimeOutException();
    }
}
