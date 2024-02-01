<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Services;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\Persistence\ObjectManager;
use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\Entity\Aggregator;
use Meilisearch\Bundle\Exception\ObjectIdNotFoundException;
use Meilisearch\Bundle\Exception\SearchHitsNotFoundException;
use Meilisearch\Bundle\SearchableEntity;
use Meilisearch\Bundle\SearchService;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

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

    public function __construct(NormalizerInterface $normalizer, Engine $engine, array $configuration, ?PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->normalizer = $normalizer;
        $this->engine = $engine;
        $this->configuration = new Collection($configuration);
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();

        $this->setSearchableEntities();
        $this->setAggregatorsAndEntitiesAggregators();
        $this->setClassToSerializerGroup();
        $this->setIndexIfMapping();
    }

    public function isSearchable($className): bool
    {
        $className = $this->getBaseClassName($className);

        return in_array($className, $this->searchableEntities, true);
    }

    public function getSearchable(): array
    {
        return $this->searchableEntities;
    }

    public function getConfiguration(): Collection
    {
        return $this->configuration;
    }

    public function searchableAs(string $className): string
    {
        $className = $this->getBaseClassName($className);

        $indexes = new Collection($this->getConfiguration()->get('indices'));
        $index = $indexes->firstWhere('class', $className);

        return $this->getConfiguration()->get('prefix').$index['name'];
    }

    public function index(ObjectManager $objectManager, $searchable): array
    {
        $searchable = is_array($searchable) ? $searchable : [$searchable];
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

        if (count($dataToRemove) > 0) {
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
        $searchable = is_array($searchable) ? $searchable : [$searchable];
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
        $this->assertIsSearchable($className);

        return $this->engine->clear($this->searchableAs($className));
    }

    public function deleteByIndexName(string $indexName): ?array
    {
        return $this->engine->delete($indexName);
    }

    public function delete(string $className): ?array
    {
        $this->assertIsSearchable($className);

        return $this->engine->delete($this->searchableAs($className));
    }

    public function search(
        ObjectManager $objectManager,
        string $className,
        string $query = '',
        array $searchParams = []
    ): array {
        $this->assertIsSearchable($className);

        $ids = $this->engine->search($query, $this->searchableAs($className), $searchParams + ['limit' => $this->configuration['nbResults']]);
        $results = [];

        // Check if the engine returns results in "hits" key
        if (!isset($ids[self::RESULT_KEY_HITS])) {
            throw new SearchHitsNotFoundException(sprintf('There is no "%s" key in the search results.', self::RESULT_KEY_HITS));
        }

        foreach ($ids[self::RESULT_KEY_HITS] as $hit) {
            if (!isset($hit[self::RESULT_KEY_OBJECTID])) {
                throw new ObjectIdNotFoundException(sprintf('There is no "%s" key in the result.', self::RESULT_KEY_OBJECTID));
            }

            $documentId = $hit[self::RESULT_KEY_OBJECTID];
            $entityClass = $className;

            if (in_array($className, $this->aggregators, true)) {
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
        array $searchParams = []
    ): array {
        $this->assertIsSearchable($className);

        return $this->engine->search($query, $this->searchableAs($className), $searchParams);
    }

    public function count(string $className, string $query = '', array $searchParams = []): int
    {
        $this->assertIsSearchable($className);

        return $this->engine->count($query, $this->searchableAs($className), $searchParams);
    }

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

    private function getBaseClassName($object_or_class): string
    {
        foreach ($this->searchableEntities as $class) {
            if (is_a($object_or_class, $class, true)) {
                return $class;
            }
        }

        if (is_object($object_or_class)) {
            return ClassUtils::getClass($object_or_class);
        }

        return $object_or_class;
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

                    $this->entitiesAggregators[$entityClass][] = $index['class'];
                    $this->aggregators[] = $index['class'];
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
            $entityClassName = ClassUtils::getClass($entity);
            if (array_key_exists($entityClassName, $this->entitiesAggregators)) {
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
        callable $operation
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
}
