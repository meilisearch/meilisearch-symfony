<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Services;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Mapping\LegacyReflectionFields;
use Doctrine\ORM\Proxy\DefaultProxyClassNameResolver;
use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\DataProvider\DataProviderInterface;
use Meilisearch\Bundle\DataProvider\DataProviderRegistryInterface;
use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\Exception\NotSearchableException;
use Meilisearch\Bundle\Model\Aggregator;
use Meilisearch\Bundle\Model\SearchResults;
use Meilisearch\Bundle\SearchableObject;
use Meilisearch\Bundle\SearchManagerInterface;
use Meilisearch\Contracts\Task;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @phpstan-import-type SearchResponse from Engine
 */
final class MeilisearchManager implements SearchManagerInterface
{
    private Collection $configuration;

    /**
     * @var list<class-string>
     */
    private array $searchables;

    /**
     * @var array<class-string, list<array{class: class-string, index: string}>>
     */
    private array $entitiesAggregators;

    /**
     * @var list<class-string<Aggregator>>
     */
    private array $aggregators;

    /**
     * @todo: config shape
     *
     * @param array $configuration
     */
    public function __construct(
        private readonly NormalizerInterface $normalizer,
        private readonly Engine $engine,
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly DataProviderRegistryInterface $dataProviderRegistry,
        array $configuration,
    ) {
        $this->configuration = new Collection($configuration);

        $this->setSearchables();
        $this->setAggregatorsAndEntitiesAggregators();
    }

    public function isSearchable(object|string $className): bool
    {
        $className = $this->getBaseClassName($className);

        return \in_array($className, $this->searchables, true);
    }

    public function searchableAs(string $className): string
    {
        $baseClassName = $this->getBaseClassName($className);

        $indexes = new Collection($this->getConfiguration()->get('indices'));
        $index = $indexes->firstWhere('class', $baseClassName);

        if (null === $index) {
            throw new NotSearchableException($baseClassName);
        }

        return $this->getConfiguration()->get('prefix').$index['name'];
    }

    public function getConfiguration(): Collection
    {
        return $this->configuration;
    }

    public function index(object|array $searchable): array
    {
        $searchable = \is_array($searchable) ? $searchable : [$searchable];
        $batches = [];

        foreach ($searchable as $entity) {
            $matchingIndices = $this->getIndicesForEntity($entity);

            foreach ($matchingIndices as $indexConfig) {
                $indexName = $indexConfig['name'];
                $configClass = $indexConfig['class'];
                $baseClass = $this->getBaseClassName($entity);

                $provider = $this->dataProviderRegistry->getDataProvider($indexName, $baseClass);

                $objectToIndex = $entity;
                if (!$entity instanceof $configClass && \in_array($configClass, $this->aggregators, true)) {
                    $objectToIndex = new $configClass(
                        $entity,
                        $provider->getIdentifierValues($entity),
                        $indexConfig['primary_key'],
                    );
                }

                if (!$this->shouldBeIndexed($objectToIndex, $indexConfig)) {
                    continue;
                }

                if (!isset($batches[$indexName])) {
                    $batches[$indexName] = [
                        'config' => $indexConfig,
                        'items' => [],
                    ];
                }

                $batches[$indexName]['items'][] = [
                    'object' => $objectToIndex,
                    'provider' => $provider,
                    'original_source' => $entity,
                ];
            }
        }

        $responses = [];
        foreach ($batches as $batch) {
            $responses[] = $this->batchProcess($batch['config'], $batch['items']);
        }

        return array_merge(...$responses);
    }

    public function remove(object|array $searchable): array
    {
        $searchable = \is_array($searchable) ? $searchable : [$searchable];
        $batches = [];

        foreach ($searchable as $entity) {
            $matchingIndices = $this->getIndicesForEntity($entity);

            foreach ($matchingIndices as $indexConfig) {
                $indexName = $indexConfig['name'];
                $prefixedIndexName = $this->configuration->get('prefix').$indexName;
                $baseClass = $this->getBaseClassName($entity);
                $provider = $this->dataProviderRegistry->getDataProvider($indexName, $baseClass);

                $batches[$indexName][] = new SearchableObject(
                    $prefixedIndexName,
                    $indexConfig['primary_key'],
                    $entity,
                    $provider->normalizeIdentifiers($provider->getIdentifierValues($entity)),
                    $this->normalizer,
                    ['groups' => []]
                );
            }
        }

        $responses = [];
        foreach ($batches as $objects) {
            foreach (array_chunk($objects, $this->configuration->get('batchSize')) as $chunk) {
                $responses[] = $this->engine->remove($chunk);
            }
        }

        return array_merge(...$responses);
    }

    public function clear(string $className): Task
    {
        $this->assertIsSearchable($className);

        return $this->engine->clear($this->searchableAs($className));
    }

    public function deleteByIndexName(string $indexName): Task
    {
        return $this->engine->delete($indexName);
    }

    public function delete(string $className): Task
    {
        $this->assertIsSearchable($className);

        return $this->engine->delete($this->searchableAs($className));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return SearchResults<T>
     */
    public function search(
        string $className,
        string $query = '',
        array $searchParams = [],
    ): SearchResults {
        $this->assertIsSearchable($className);

        $response = $this->engine->search($query, $this->searchableAs($className), $searchParams + ['limit' => $this->configuration->get('nbResults')]);
        $response['raw'] = $response;
        $hits = $response[self::RESULT_KEY_HITS];

        if ([] === $hits) {
            /** @var SearchResults<T> */
            return new SearchResults(...$response);
        }

        $baseClassName = $this->getBaseClassName($className);
        $indexes = new Collection($this->getConfiguration()->get('indices'));
        $index = $indexes->firstWhere('class', $baseClassName);
        $primaryKey = $index['primary_key'];

        $identifiers = array_column($hits, $primaryKey);

        $dataProvider = $this->dataProviderRegistry->getDataProvider($index['name'], $baseClassName);
        $loadedObjects = $dataProvider->loadByIdentifiers($identifiers);

        $objectsById = [];
        foreach ($loadedObjects as $object) {
            $key = (string) $dataProvider->normalizeIdentifiers($dataProvider->getIdentifierValues($object));
            $objectsById[$key] = $object;
        }

        $results = [];

        foreach ($hits as $hit) {
            $documentId = (string) $hit[$primaryKey];

            if (isset($objectsById[$documentId])) {
                $results[] = $objectsById[$documentId];
            }
        }

        $response['hits'] = $results;

        /** @var SearchResults<T> */
        return new SearchResults(...$response);
    }

    public function rawSearch(
        string $className,
        string $query = '',
        array $searchParams = [],
    ): array {
        $this->assertIsSearchable($className);

        return $this->engine->search($query, $this->searchableAs($className), $searchParams);
    }

    public function count(string $className, string $query = '', array $searchParams = []): int
    {
        $this->assertIsSearchable($className);

        return $this->engine->count($query, $this->searchableAs($className), $searchParams);
    }

    /**
     * @param array{index_if: string|null} $indexConfig
     */
    private function shouldBeIndexed(object $entity, array $indexConfig): bool
    {
        if (null === $indexConfig['index_if']) {
            return true;
        }

        $propertyPath = $indexConfig['index_if'];

        if ($this->propertyAccessor->isReadable($entity, $propertyPath)) {
            return (bool) $this->propertyAccessor->getValue($entity, $propertyPath);
        }

        return false;
    }

    /**
     * @param object|class-string $objectOrClass
     *
     * @return class-string
     */
    private function getBaseClassName(object|string $objectOrClass): string
    {
        if (\is_object($objectOrClass)) {
            return self::resolveClass($objectOrClass);
        }

        return $objectOrClass;
    }

    private function setSearchables(): void
    {
        $this->searchables = array_unique(array_column($this->configuration->get('indices'), 'class'));
    }

    private function setAggregatorsAndEntitiesAggregators(): void
    {
        $this->entitiesAggregators = [];
        $this->aggregators = [];

        foreach ($this->configuration->get('indices') as $index) {
            $className = $index['class'];

            if (is_subclass_of($className, Aggregator::class)) {
                foreach ($className::getEntities() as $entityClass) {
                    $this->entitiesAggregators[$entityClass] ??= [];
                    $this->entitiesAggregators[$entityClass][] = [
                        'class' => $className,
                        'index' => $index['name'],
                    ];
                    $this->aggregators[] = (string) $className;
                }
            }
        }

        $this->aggregators = array_unique($this->aggregators);
    }

    /**
     * @param array{
     *     name: non-empty-string,
     *     enable_serializer_groups: bool,
     *     serializer_groups: array<string>,
     *     primary_key: string,
     * } $indexConfig
     * @param array<array{
     *     object: object,
     *     provider: DataProviderInterface,
     *     original_source: object
     * }> $items
     */
    private function batchProcess(array $indexConfig, array $items): array
    {
        $batch = [];
        $indexName = $indexConfig['name'];
        $prefixedIndexName = $this->configuration->get('prefix').$indexName;

        $normalizationContext = [];
        if (true === $indexConfig['enable_serializer_groups']) {
            $normalizationContext['groups'] = $indexConfig['serializer_groups'];
        }

        foreach (array_chunk($items, $this->configuration->get('batchSize')) as $chunk) {
            $searchableChunk = [];

            foreach ($chunk as $item) {
                $object = $item['object'];
                $provider = $item['provider'];
                $originalSource = $item['original_source'];

                $searchableChunk[] = new SearchableObject(
                    $prefixedIndexName,
                    $indexConfig['primary_key'],
                    $object,
                    $provider->normalizeIdentifiers($provider->getIdentifierValues($originalSource)),
                    $this->normalizer,
                    $normalizationContext,
                );
            }

            $response = $this->engine->index($searchableChunk);

            if ([] !== $response) {
                $batch[] = $response;
            }
        }

        return $batch;
    }

    private function assertIsSearchable(string $className): void
    {
        if (!$this->isSearchable($className)) {
            throw new NotSearchableException($className);
        }
    }

    /**
     * Returns ALL index configurations that apply to this entity.
     */
    private function getIndicesForEntity(object $entity): array
    {
        $className = $this->getBaseClassName($entity);
        $matchingConfigs = [];

        foreach ($this->configuration->get('indices') as $config) {
            $configClass = $config['class'];

            if ($className === $configClass || is_subclass_of($className, $configClass)) {
                $matchingConfigs[] = $config;
            }

            if (isset($this->entitiesAggregators[$className])) {
                foreach ($this->entitiesAggregators[$className] as $aggInfo) {
                    if ($aggInfo['class'] === $configClass && $aggInfo['index'] === $config['name']) {
                        $matchingConfigs[] = $config;
                    }
                }
            }
        }

        return $matchingConfigs;
    }

    private static function resolveClass(object $object): string
    {
        static $resolver;

        $resolver ??= (static function () {
            // Native lazy objects compatibility
            if (PHP_VERSION_ID >= 80400 && class_exists(LegacyReflectionFields::class)) {
                return fn (object $object) => $object::class;
            }

            // Doctrine ORM v3+ compatibility
            if (class_exists(DefaultProxyClassNameResolver::class)) {
                return fn (object $object) => DefaultProxyClassNameResolver::getClass($object);
            }

            // Legacy Doctrine ORM compatibility
            return fn (object $object) => ClassUtils::getClass($object); // @codeCoverageIgnore
        })();

        return $resolver($object);
    }
}
