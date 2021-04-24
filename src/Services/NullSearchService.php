<?php

namespace MeiliSearch\Bundle\Services;

use Doctrine\Persistence\ObjectManager;
use MeiliSearch\Bundle\SearchService;
use stdClass;

/**
 * Class NullSearchService.
 *
 * @package MeiliSearch\Bundle\Services
 */
class NullSearchService implements SearchService
{
    /**
     * {@inheritdoc}
     */
    public function isSearchable($className): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getSearchable(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(): array
    {
        return ['batchSize' => 200];
    }

    /**
     * {@inheritdoc}
     */
    public function searchableAs(string $className): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function index(ObjectManager $objectManager, $searchable): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function remove(ObjectManager $objectManager, $searchable): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function clear(string $className): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $className): ?array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function search(
        ObjectManager $objectManager,
        string $className,
        string $query = '',
        array $searchParams = []
    ): array {
        return [new stdClass()];
    }

    /**
     * {@inheritdoc}
     */
    public function rawSearch(
        string $className,
        string $query = '',
        array $searchParams = []
    ): array {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $className, string $query = '', array $searchParams = []): int
    {
        return 0;
    }
}
