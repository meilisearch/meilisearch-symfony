<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Services;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Mapping\LegacyReflectionFields;
use Doctrine\ORM\Proxy\DefaultProxyClassNameResolver;
use Doctrine\Persistence\ObjectManager;
use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\Entity\Aggregator;
use Meilisearch\Bundle\Exception\ObjectIdNotFoundException;
use Meilisearch\Bundle\Exception\SearchHitsNotFoundException;
use Meilisearch\Bundle\SearchableEntity;
use Meilisearch\Bundle\SearchManagerInterface;
use Meilisearch\Bundle\SearchService;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @deprecated Since 0.16, use `Meilisearch\Bundle\Services\MeilisearchManager` instead.
 */
final class MeilisearchService implements SearchService
{
    private NormalizerInterface $normalizer;
    private Engine $engine;
    private Collection $configuration;
    private PropertyAccessorInterface $propertyAccessor;
    /**
     * @var list<class-string>
     */
    private array $searchableEntities;
    /**
     * @var array<class-string, list<class-string>>
     */
    private array $entitiesAggregators;
    /**
     * @var list<class-string<Aggregator>>
     */
    private array $aggregators;
    /**
     * @var array<class-string, array<string>>
     */
    private array $classToSerializerGroup;
    private array $indexIfMapping;
    private ?SearchManagerInterface $manager;

    public function __construct(NormalizerInterface $normalizer, Engine $engine, array $configuration, ?PropertyAccessorInterface $propertyAccessor = null, ?SearchManagerInterface $manager = null)
    {
        $this->normalizer = $normalizer;
        $this->engine = $engine;
        $this->configuration = new Collection($configuration);
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
        $this->manager = $manager;

        $this->setSearchableEntities();
        $this->setAggregatorsAndEntitiesAggregators();
        $this->setClassToSerializerGroup();
        $this->setIndexIfMapping();
    }

    public function isSearchable($className): bool
    {
        trigger_deprecation('meilisearch/meilisearch-symfony', '0.16', 'Using `Meilisearch\Bundle\Services\MeilisearchService::isSearchable()` is deprecated. Use `Meilisearch\Bundle\Services\MeilisearchManager::isSearchable()` instead.');

        if (null !== $this->manager) {
            return $this->manager->isSearchable($className);
        }

        $className = $this->getBaseClassName($className);

        return \in_array($className, $this->searchableEntities, true);
    }

    /**
     * @deprecated without replacement
     */
    public function getSearchable(): array
    {
        return $this->searchableEntities;
    }

    public function getConfiguration(): Collection
    {
        trigger_deprecation('meilisearch/meilisearch-symfony', '0.16', 'Using `Meilisearch\Bundle\Services\MeilisearchService::getConfiguration()` is deprecated. Use `Meilisearch\Bundle\Services\MeilisearchManager::getConfiguration()` instead.');

        if (null !== $this->manager) {
            return $this->manager->getConfiguration();
        }

        return $this->configuration;
    }

    public function searchableAs(string $className): string
    {
        trigger_deprecation('meilisearch/meilisearch-symfony', '0.16', 'Using `Meilisearch\Bundle\Services\MeilisearchService::searchableAs()` is deprecated. Use `Meilisearch\Bundle\Services\MeilisearchManager::searchableAs()` instead.');

        if (null !== $this->manager) {
            return $this->manager->searchableAs($className);
        }

        $className = $this->getBaseClassName($className);

        $indexes = new Collection($this->getConfiguration()->get('indices'));
        $index = $indexes->firstWhere('class', $className);

        return $this->getConfiguration()->get('prefix').$index['name'];
    }

    public function index(ObjectManager $objectManager, $searchable): array
    {
        trigger_deprecation('meilisearch/meilisearch-symfony', '0.16', 'Passing `Doctrine\Persistence\ObjectManager` to index() is deprecated. Use `Meilisearch\Bundle\Services\MeilisearchManager::index()` instead.');

        if (null !== $this->manager) {
            return $this->manager->index($searchable);
        }

        $searchable = \is_array($searchable) ? $searchable : [$searchable];
        $searchable = array_merge($searchable, $this->getAggregatorsFromEntities($objectManager, $searchable));

        $dataToIndex = array_filter(
            $searchable,
            fn ($entity) => $this->isSearchable($entity)
        );

        $dataToRemove = [];
        foreach ($dataToIndex as $key => $entity) {
            if (!$this->shouldBeIndexed($entity)) {
                unset($dataToIndex[$key]);
                $dataToRemove[] = $entity;
            }
        }

        if (\count($dataToRemove) > 0) {
            $this->remove($objectManager, $dataToRemove);
        }

        return $this->makeSearchServiceResponseFrom(
            $objectManager,
            $dataToIndex,
            fn ($chunk) => $this->engine->index($chunk)
        );
    }

    public function remove(ObjectManager $objectManager, $searchable): array
    {
        trigger_deprecation('meilisearch/meilisearch-symfony', '0.16', 'Passing `Doctrine\Persistence\ObjectManager` to remove() is deprecated. Use `Meilisearch\Bundle\Services\MeilisearchManager::remove()` instead.');

        if (null !== $this->manager) {
            return $this->manager->remove($searchable);
        }

        $searchable = \is_array($searchable) ? $searchable : [$searchable];
        $searchable = array_merge($searchable, $this->getAggregatorsFromEntities($objectManager, $searchable));

        $searchable = array_filter(
            $searchable,
            fn ($entity) => $this->isSearchable($entity)
        );

        return $this->makeSearchServiceResponseFrom(
            $objectManager,
            $searchable,
            fn ($chunk) => $this->engine->remove($chunk)
        );
    }

    public function clear(string $className): array
    {
        trigger_deprecation('meilisearch/meilisearch-symfony', '0.16', 'Using `Meilisearch\Bundle\Services\MeilisearchService::clear()` is deprecated. Use `Meilisearch\Bundle\Services\MeilisearchManager::clear()` instead.');

        if (null !== $this->manager) {
            return $this->manager->clear($className);
        }

        $this->assertIsSearchable($className);

        return $this->engine->clear($this->searchableAs($className));
    }

    public function deleteByIndexName(string $indexName): array
    {
        trigger_deprecation('meilisearch/meilisearch-symfony', '0.16', 'Using `Meilisearch\Bundle\Services\MeilisearchService::deleteByIndexName()` is deprecated. Use `Meilisearch\Bundle\Services\MeilisearchManager::deleteByIndexName()` instead.');

        if (null !== $this->manager) {
            return $this->manager->deleteByIndexName($indexName);
        }

        return $this->engine->delete($indexName);
    }

    public function delete(string $className): array
    {
        trigger_deprecation('meilisearch/meilisearch-symfony', '0.16', 'Using `Meilisearch\Bundle\Services\MeilisearchService::delete()` is deprecated. Use `Meilisearch\Bundle\Services\MeilisearchManager::delete()` instead.');

        if (null !== $this->manager) {
            return $this->manager->delete($className);
        }

        $this->assertIsSearchable($className);

        return $this->engine->delete($this->searchableAs($className));
    }

    public function search(
        ObjectManager $objectManager,
        string $className,
        string $query = '',
        array $searchParams = [],
    ): array {
        trigger_deprecation('meilisearch/meilisearch-symfony', '0.16', 'Passing `Doctrine\Persistence\ObjectManager` to search() is deprecated. Use `Meilisearch\Bundle\Services\MeilisearchManager::search()` instead.');

        if (null !== $this->manager) {
            return $this->manager->search($className, $query, $searchParams)->jsonSerialize();
        }

        $this->assertIsSearchable($className);

        $ids = $this->engine->search($query, $this->searchableAs($className), $searchParams + ['limit' => $this->configuration->get('nbResults')]);
        $results = [];

        // Check if the engine returns results in "hits" key
        if (!isset($ids[self::RESULT_KEY_HITS])) {
            throw new SearchHitsNotFoundException(\sprintf('There is no "%s" key in the search results.', self::RESULT_KEY_HITS));
        }

        foreach ($ids[self::RESULT_KEY_HITS] as $hit) {
            if (!isset($hit[self::RESULT_KEY_OBJECTID])) {
                throw new ObjectIdNotFoundException(\sprintf('There is no "%s" key in the result.', self::RESULT_KEY_OBJECTID));
            }

            $documentId = $hit[self::RESULT_KEY_OBJECTID];
            $entityClass = $className;

            if (\in_array($className, $this->aggregators, true)) {
                $objectId = $hit[self::RESULT_KEY_OBJECTID];
                $entityClass = $className::getEntityClassFromObjectId($objectId);
                $documentId = $className::getEntityIdFromObjectId($objectId);
            }

            $repo = $objectManager->getRepository($entityClass);
            $entity = $repo->find($documentId);

            if (null !== $entity) {
                $results[] = $entity;
            }
        }

        return $results;
    }

    public function rawSearch(
        string $className,
        string $query = '',
        array $searchParams = [],
    ): array {
        trigger_deprecation('meilisearch/meilisearch-symfony', '0.16', 'Using `Meilisearch\Bundle\Services\MeilisearchService::rawSearch()` is deprecated. Use `Meilisearch\Bundle\Services\MeilisearchManager::rawSearch()` instead.');

        if (null !== $this->manager) {
            return $this->manager->rawSearch($className, $query, $searchParams);
        }

        $this->assertIsSearchable($className);

        return $this->engine->search($query, $this->searchableAs($className), $searchParams);
    }

    public function count(string $className, string $query = '', array $searchParams = []): int
    {
        trigger_deprecation('meilisearch/meilisearch-symfony', '0.16', 'Using `Meilisearch\Bundle\Services\MeilisearchService::count()` is deprecated. Use `Meilisearch\Bundle\Services\MeilisearchManager::count()` instead.');

        if (null !== $this->manager) {
            return $this->manager->count($className, $query, $searchParams);
        }

        $this->assertIsSearchable($className);

        return $this->engine->count($query, $this->searchableAs($className), $searchParams);
    }

    /**
     * @deprecated without replacement
     */
    public function shouldBeIndexed(object $entity): bool
    {
        $className = $this->getBaseClassName($entity);

        $propertyPath = $this->indexIfMapping[$className];

        if (null !== $propertyPath) {
            if ($this->propertyAccessor->isReadable($entity, $propertyPath)) {
                return (bool) $this->propertyAccessor->getValue($entity, $propertyPath);
            }

            return false;
        }

        return true;
    }

    /**
     * @param object|class-string $objectOrClass
     *
     * @return class-string
     */
    private function getBaseClassName($objectOrClass): string
    {
        foreach ($this->searchableEntities as $class) {
            if (is_a($objectOrClass, $class, true)) {
                return $class;
            }
        }

        if (\is_object($objectOrClass)) {
            return self::resolveClass($objectOrClass);
        }

        return $objectOrClass;
    }

    private function setSearchableEntities(): void
    {
        $searchable = [];
        foreach ($this->configuration->get('indices') as $index) {
            $searchable[] = $index['class'];
        }
        $this->searchableEntities = array_unique($searchable);
    }

    private function setAggregatorsAndEntitiesAggregators(): void
    {
        $this->entitiesAggregators = [];
        $this->aggregators = [];

        foreach ($this->configuration->get('indices') as $index) {
            if (is_subclass_of($index['class'], Aggregator::class)) {
                foreach ($index['class']::getEntities() as $entityClass) {
                    if (!isset($this->entitiesAggregators[$entityClass])) {
                        $this->entitiesAggregators[$entityClass] = [];
                    }

                    $this->entitiesAggregators[$entityClass][] = (string) $index['class'];
                    $this->aggregators[] = (string) $index['class'];
                }
            }
        }

        $this->aggregators = array_unique($this->aggregators);
    }

    private function setClassToSerializerGroup(): void
    {
        $mapping = [];

        /** @var array $indexDetails */
        foreach ($this->configuration->get('indices') as $indexDetails) {
            $mapping[$indexDetails['class']] = $indexDetails['enable_serializer_groups'] ? $indexDetails['serializer_groups'] : [];
        }
        $this->classToSerializerGroup = $mapping;
    }

    private function setIndexIfMapping(): void
    {
        $mapping = [];

        /** @var array $indexDetails */
        foreach ($this->configuration->get('indices') as $indexDetails) {
            $mapping[$indexDetails['class']] = $indexDetails['index_if'];
        }
        $this->indexIfMapping = $mapping;
    }

    /**
     * Returns the aggregators instances of the provided entities.
     *
     * @param array<int, object> $entities
     *
     * @return array<int, object>
     */
    private function getAggregatorsFromEntities(ObjectManager $objectManager, array $entities): array
    {
        $aggregators = [];

        foreach ($entities as $entity) {
            $entityClassName = self::resolveClass($entity);
            if (\array_key_exists($entityClassName, $this->entitiesAggregators)) {
                foreach ($this->entitiesAggregators[$entityClassName] as $aggregator) {
                    $aggregators[] = new $aggregator(
                        $entity,
                        $objectManager->getClassMetadata($entityClassName)->getIdentifierValues($entity)
                    );
                }
            }
        }

        return $aggregators;
    }

    /**
     * For each chunk performs the provided operation.
     *
     * @param array<int, object> $entities
     */
    private function makeSearchServiceResponseFrom(
        ObjectManager $objectManager,
        array $entities,
        callable $operation,
    ): array {
        $batch = [];
        foreach (array_chunk($entities, $this->configuration->get('batchSize')) as $chunk) {
            $searchableEntitiesChunk = [];
            foreach ($chunk as $entity) {
                $entityClassName = $this->getBaseClassName($entity);

                $searchableEntitiesChunk[] = new SearchableEntity(
                    $this->searchableAs($entityClassName),
                    $entity,
                    $objectManager->getClassMetadata($entityClassName),
                    $this->normalizer,
                    ['normalizationGroups' => $this->getNormalizationGroups($entityClassName)]
                );
            }

            $batch[] = $operation($searchableEntitiesChunk);
        }

        return $batch;
    }

    /**
     * @param class-string $className
     *
     * @return list<string>
     */
    private function getNormalizationGroups(string $className): array
    {
        return $this->classToSerializerGroup[$className];
    }

    private function assertIsSearchable(string $className): void
    {
        if (!$this->isSearchable($className)) {
            throw new Exception('Class '.$className.' is not searchable.');
        }
    }

    private static function resolveClass(object $object): string
    {
        static $resolver;

        $resolver ??= (function () {
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
