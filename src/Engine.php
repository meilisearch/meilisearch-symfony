<?php

namespace MeiliSearch\Bundle;

use MeiliSearch\Client;
use MeiliSearch\Exceptions\HTTPRequestException;
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
     * @throws HTTPRequestException
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

            $indexName = $entity->getIndexName();

            if (!isset($data[$indexName])) {
                $data[$indexName] = [];
            }

            $data[$indexName][] = $searchableArray + ['objectID' => $entity->getId()];
        }

        $result = [];
        foreach ($data as $indexName => $objects) {
            $result[$indexName] = $this->client
                ->getOrCreateIndex($indexName, ['primaryKey' => 'objectID'])
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
            $indexName = $entity->getIndexName();

            if (!isset($data[$indexName])) {
                $data[$indexName] = [];
            }

            $data[$indexName][] = $entity->getId();
        }

        $result = [];
        foreach ($data as $indexName => $objects) {
            $result[$indexName] = $this->client
                ->getIndex($indexName)
                ->deleteDocument(reset($objects));
        }

        return $result;
    }

    /**
     * Clear the records of an index.
     * This method enables you to delete an index’s contents (records).
     *
     * @param string $indexName
     *
     * @return array
     *
     * @throws HTTPRequestException
     */
    public function clear(string $indexName): array
    {
        $index = $this->client->getOrCreateIndex($indexName);
        $return = $index->deleteAllDocuments();

        return $index->getUpdateStatus($return['updateId']);
    }

    /**
     * Delete an index and it's content.
     *
     * @param string $indexName
     *
     * @return array|null
     */
    public function delete(string $indexName): ?array
    {
        return $this->client->deleteIndex($indexName);
    }

    /**
     * Method used for querying an index.
     *
     * @param string $query
     * @param string $indexName
     * @param array  $requestOptions
     *
     * @return array
     */
    public function search(string $query, string $indexName, array $requestOptions): array
    {
        if ('' === $query) {
            $query = null;
        }

        return $this->client->getIndex($indexName)->search($query, $requestOptions);
    }

    /**
     * Search the index and returns the number of results.
     *
     * @param string $query
     * @param string $indexName
     * @param array  $requestOptions
     *
     * @return int
     */
    public function count(string $query, string $indexName, array $requestOptions): int
    {
        return (int) $this->client->getIndex($indexName)->search($query, $requestOptions)['nbHits'];
    }
}
