<?php

namespace MeiliSearch\Bundle;

use MeiliSearch\Client;
use MeiliSearch\Exceptions\ApiException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use function count;
use function reset;

/**
 * Class Engine.
 *
 * @package MeiliSearch\Bundle
 */
final class Engine
{
    /** @var Client */
    private $client;

    /**
     * Engine constructor.
     *
     * @param Client $client
     */
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
     * @return array
     *
     * @throws ApiException
     * @throws ExceptionInterface
     */
    public function index($searchableEntities): array
    {
        if ($searchableEntities instanceof SearchableEntity) {
            $searchableEntities = [$searchableEntities];
        }

        $data = [];
        foreach ($searchableEntities as $entity) {
            $searchableArray = $entity->getSearchableArray();
            if (null === $searchableArray || 0 === count($searchableArray)) {
                continue;
            }

            $indexUid = $entity->getIndexUid();

            if (!isset($data[$indexUid])) {
                $data[$indexUid] = [];
            }

            $data[$indexUid][] = $searchableArray + ['objectID' => $entity->getId()];
        }

        $result = [];
        foreach ($data as $indexUid => $objects) {
            $result[$indexUid] = $this->client
                ->getOrCreateIndex($indexUid, ['primaryKey' => 'objectID'])
                ->addDocuments($objects);
        }

        return $result;
    }

    /**
     * Remove objects from an index using their object UIDs.
     * This method enables you to remove one or more objects from an index.
     *
     * @param array|SearchableEntity $searchableEntities
     *
     * @return array
     *
     * @throws ExceptionInterface
     */
    public function remove($searchableEntities): array
    {
        if ($searchableEntities instanceof SearchableEntity) {
            $searchableEntities = [$searchableEntities];
        }

        $data = [];
        foreach ($searchableEntities as $entity) {
            $searchableArray = $entity->getSearchableArray();
            if (null === $searchableArray || 0 === count($searchableArray)) {
                continue;
            }
            $indexUid = $entity->getIndexName();

            if (!isset($data[$indexUid])) {
                $data[$indexUid] = [];
            }

            $data[$indexUid][] = $entity->getId();
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
     *
     * @param string $indexUid
     *
     * @return array
     *
     * @throws ApiException
     */
    public function clear(string $indexUid): array
    {
        $index = $this->client->getOrCreateIndex($indexUid);
        $return = $index->deleteAllDocuments();

        return $index->getUpdateStatus($return['updateId']);
    }

    /**
     * Delete an index and it's content.
     *
     * @param string $indexUid
     *
     * @return array|null
     */
    public function delete(string $indexUid): ?array
    {
        return $this->client->deleteIndex($indexUid);
    }

    /**
     * Method used for querying an index.
     *
     * @param string $query
     * @param string $indexUid
     * @param array  $searchParams
     *
     * @return array
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
        return (int) $this->client->index($indexName)->search($query, $searchParams)['nbHits'];
    }
}
