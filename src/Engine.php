<?php

declare(strict_types=1);

namespace Meilisearch\Bundle;

use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;

final class Engine
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Add new objects to an index.
     * This method allows you to create records on your index by sending one or more objects.
     * Each object contains a set of attributes and values, which represents a full record on an index.
     *
     * @param array|SearchableEntity $searchableEntities
     *
     * @throws ApiException
     */
    public function index($searchableEntities): array
    {
        if ($searchableEntities instanceof SearchableEntity) {
            /** @var SearchableEntity[] $searchableEntities */
            $searchableEntities = [$searchableEntities];
        }

        $data = [];
        foreach ($searchableEntities as $entity) {
            $searchableArray = $entity->getSearchableArray();
            if (null === $searchableArray || 0 === \count($searchableArray)) {
                continue;
            }

            $indexUid = $entity->getIndexUid();

            if (!isset($data[$indexUid])) {
                $data[$indexUid] = [];
            }

            $data[$indexUid][] = $searchableArray + ['objectID' => $this->normalizeId($entity->getId())];
        }

        $result = [];
        foreach ($data as $indexUid => $objects) {
            $result[$indexUid] = $this->client
                ->index($indexUid)
                ->addDocuments($objects, 'objectID');
        }

        return $result;
    }

    /**
     * Remove objects from an index using their object UIDs.
     * This method enables you to remove one or more objects from an index.
     *
     * @param array|SearchableEntity $searchableEntities
     */
    public function remove($searchableEntities): array
    {
        if ($searchableEntities instanceof SearchableEntity) {
            /** @var SearchableEntity[] $searchableEntities */
            $searchableEntities = [$searchableEntities];
        }

        $data = [];

        /** @var SearchableEntity $entity */
        foreach ($searchableEntities as $entity) {
            $searchableArray = $entity->getSearchableArray();
            if (0 === \count($searchableArray)) {
                continue;
            }
            $indexUid = $entity->getIndexUid();

            if (!isset($data[$indexUid])) {
                $data[$indexUid] = [];
            }

            $data[$indexUid][] = $this->normalizeId($entity->getId());
        }

        $result = [];
        foreach ($data as $indexUid => $objects) {
            $result[$indexUid] = $this->client
                ->index($indexUid)
                ->deleteDocument(reset($objects));
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
    public function clear(string $indexUid): array
    {
        $index = $this->client->index($indexUid);
        $task = $index->deleteAllDocuments();

        return $task;
    }

    /**
     * Delete an index and its content.
     */
    public function delete(string $indexUid): ?array
    {
        return $this->client->deleteIndex($indexUid);
    }

    /**
     * Method used for querying an index.
     */
    public function search(string $query, string $indexUid, array $searchParams): array
    {
        if ('' === $query) {
            $query = null;
        }

        return $this->client->index($indexUid)->rawSearch($query, $searchParams);
    }

    /**
     * Search the index and returns the number of results.
     */
    public function count(string $query, string $indexName, array $searchParams): int
    {
        return $this->client->index($indexName)->search($query, $searchParams)->getHitsCount();
    }

    private function normalizeId($id)
    {
        if (is_object($id) && method_exists($id, '__toString')) {
            return (string) $id;
        }

        return $id;
    }
}
