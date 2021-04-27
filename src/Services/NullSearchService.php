<?php

declare(strict_types=1);

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

    public function getSearchable(): array
    {
        return [];
    }

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

    public function index(ObjectManager $objectManager, $searchable): array
    {
        return [];
    }

    public function remove(ObjectManager $objectManager, $searchable): array
    {
        return [];
    }

    public function clear(string $className): array
    {
        return [];
    }

    public function delete(string $className): ?array
    {
        return [];
    }

    public function search(
        ObjectManager $objectManager,
        string $className,
        string $query = '',
        array $requestOptions = []
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

    public function count(string $className, string $query = '', array $requestOptions = []): int
    {
        return 0;
    }
}
