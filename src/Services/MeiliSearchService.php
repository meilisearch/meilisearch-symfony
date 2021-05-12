<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Services;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\Persistence\ObjectManager;
use MeiliSearch\Bundle\Engine;
use MeiliSearch\Bundle\Entity\Aggregator;
use MeiliSearch\Bundle\Exception\SearchHitsNotFoundException;
use MeiliSearch\Bundle\SearchableEntity;
use MeiliSearch\Bundle\SearchService;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class MeiliSearchService.
 */
final class MeiliSearchService implements SearchService
{
    private SerializerInterface $normalizer;
    private Engine $engine;
    private array $configuration;
    private PropertyAccessor $propertyAccessor;
    private array $searchableEntities;
    private array $entitiesAggregators;
    private array $aggregators;
    private array $classToIndexMapping;
    private array $classToSerializerGroupMapping;
    private array $indexIfMapping;
    private array $settingsMapping;

    public function __construct(SerializerInterface $normalizer, Engine $engine, array $configuration)
    {
        $this->normalizer = $normalizer;
        $this->engine = $engine;
        $this->configuration = $configuration;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();

        $this->setSearchableEntities();
        $this->setAggregatorsAndEntitiesAggregators();
        $this->setClassToIndexMapping();
        $this->setClassToSerializerGroupMapping();
        $this->setIndexIfMapping();
        $this->setSettingsMapping();
    }

    /**
     * {@inheritdoc}
     */
    public function isSearchable($className): bool
    {
        if (is_object($className)) {
            $className = ClassUtils::getClass($className);
        }

        return in_array($className, $this->searchableEntities, true);
    }

    public function getSearchable(): array
    {
        return $this->searchableEntities;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function searchableAs(string $className): string
    {
        return $this->configuration['prefix'].$this->classToIndexMapping[$className];
    }

    public function index(ObjectManager $objectManager, $searchable): array
    {
        $searchable = is_array($searchable) ? $searchable : [$searchable];
        $searchable = array_merge($searchable, $this->getAggregatorsFromEntities($objectManager, $searchable));

        $searchableToBeIndexed = array_filter(
            $searchable,
            function ($entity) {
                return $this->isSearchable($entity);
            }
        );

        $searchableToBeRemoved = [];
        foreach ($searchableToBeIndexed as $key => $entity) {
            if (!$this->shouldBeIndexed($entity)) {
                unset($searchableToBeIndexed[$key]);
                $searchableToBeRemoved[] = $entity;
            }
        }

        if (count($searchableToBeRemoved) > 0) {
            $this->remove($objectManager, $searchableToBeRemoved);
        }

        return $this->makeSearchServiceResponseFrom(
            $objectManager,
            $searchableToBeIndexed,
            function ($chunk) {
                return $this->engine->index($chunk);
            }
        );
    }

    public function remove(ObjectManager $objectManager, $searchable): array
    {
        $searchable = is_array($searchable) ? $searchable : [$searchable];
        $searchable = array_merge($searchable, $this->getAggregatorsFromEntities($objectManager, $searchable));

        $searchable = array_filter(
            $searchable,
            function ($entity) {
                return $this->isSearchable($entity);
            }
        );

        return $this->makeSearchServiceResponseFrom(
            $objectManager,
            $searchable,
            function ($chunk) {
                return $this->engine->remove($chunk);
            }
        );
    }

    public function clear(string $className): array
    {
        $this->assertIsSearchable($className);

        return $this->engine->clear($this->searchableAs($className));
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

        $ids = $this->engine->search($query, $this->searchableAs($className), $searchParams);
        $results = [];

        // Check if the engine returns results in "hits" key
        if (!isset($ids['hits'])) {
            throw new SearchHitsNotFoundException('There is no "hits" key in the search results.');
        }

        foreach ($ids['hits'] as $objectID) {
            if (in_array($className, $this->aggregators, true)) {
                $entityClass = $className::getEntityClassFromObjectId($objectID);
                $id = $className::getEntityIdFromObjectId($objectID);
            } else {
                $id = $objectID;
                $entityClass = $className;
            }

            $repo = $objectManager->getRepository($entityClass);
            $entity = $repo->findOneBy(['id' => $id]);

            if (null !== $entity) {
                $results[] = $entity;
            }
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * @param object $entity
     */
    public function shouldBeIndexed($entity): bool
    {
        $className = ClassUtils::getClass($entity);
        $propertyPath = $this->indexIfMapping[$className];

        if (null !== $propertyPath) {
            if ($this->propertyAccessor->isReadable($entity, $propertyPath)) {
                return (bool) $this->propertyAccessor->getValue($entity, $propertyPath);
            }

            return false;
        }

        return true;
    }

    private function setSearchableEntities(): void
    {
        $searchable = [];
        foreach ($this->configuration['indices'] as $name => $index) {
            $searchable[] = $index['class'];
        }
        $this->searchableEntities = array_unique($searchable);
    }

    private function setAggregatorsAndEntitiesAggregators(): void
    {
        $this->entitiesAggregators = [];
        $this->aggregators = [];

        foreach ($this->configuration['indices'] as $index) {
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

    private function setClassToIndexMapping(): void
    {
        $mapping = [];
        foreach ($this->configuration['indices'] as $indexName => $indexDetails) {
            $mapping[$indexDetails['class']] = $indexName;
        }
        $this->classToIndexMapping = $mapping;
    }

    private function setClassToSerializerGroupMapping(): void
    {
        $mapping = [];
        foreach ($this->configuration['indices'] as $indexDetails) {
            $mapping[$indexDetails['class']] = $indexDetails['enable_serializer_groups'];
        }
        $this->classToSerializerGroupMapping = $mapping;
    }

    private function setIndexIfMapping(): void
    {
        $mapping = [];
        foreach ($this->configuration['indices'] as $indexDetails) {
            $mapping[$indexDetails['class']] = $indexDetails['index_if'];
        }
        $this->indexIfMapping = $mapping;
    }

    private function setSettingsMapping(): void
    {
        $mapping = [];
        foreach ($this->configuration['indices'] as $indexDetails) {
            $mapping[$indexDetails['class']] = $indexDetails['settings'];
        }
        $this->settingsMapping = $mapping;
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
        foreach (array_chunk($entities, $this->configuration['batchSize']) as $chunk) {
            $searchableEntitiesChunk = [];
            foreach ($chunk as $entity) {
                $entityClassName = ClassUtils::getClass($entity);

                $searchableEntitiesChunk[] = new SearchableEntity(
                    $this->searchableAs($entityClassName),
                    $entity,
                    $objectManager->getClassMetadata($entityClassName),
                    $this->normalizer,
                    ['useSerializerGroup' => $this->canUseSerializerGroup($entityClassName)]
                );
            }

            $batch[] = $operation($searchableEntitiesChunk);
        }

        return $batch;
    }

    private function canUseSerializerGroup(string $className): bool
    {
        return $this->classToSerializerGroupMapping[$className];
    }

    private function assertIsSearchable(string $className): void
    {
        if (!$this->isSearchable($className)) {
            throw new Exception('Class '.$className.' is not searchable.');
        }
    }
}
