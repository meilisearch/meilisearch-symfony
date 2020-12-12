<?php

namespace MeiliSearch\Bundle\Services;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\Persistence\ObjectManager;
use MeiliSearch\Bundle\Engine;
use MeiliSearch\Bundle\Entity\Aggregator;
use MeiliSearch\Bundle\SearchableEntity;
use MeiliSearch\Bundle\SearchService;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\SerializerInterface;
use function array_chunk;
use function array_filter;
use function array_key_exists;
use function array_merge;
use function array_unique;
use function count;
use function in_array;
use function is_array;
use function is_object;
use function is_subclass_of;

/**
 * Class MeiliSearchService.
 *
 * @package MeiliSearch\Bundle\Services
 */
final class MeiliSearchService implements SearchService
{
    /** @var */
    protected $normalizer;

    /** @var Engine */
    protected $engine;

    /** @var array */
    protected $configuration;

    /** @var PropertyAccessor */
    private $propertyAccessor;

    /** @var array */
    protected $searchableEntities;

    /** @var array */
    protected $entitiesAggregators;

    /** @var array */
    protected $aggregators;

    /** @var array */
    protected $classToIndexMapping;

    /** @var array */
    protected $classToSerializerGroupMapping;

    /** @var array */
    protected $indexIfMapping;

    /** @var array */
    protected $settingsMapping;

    /**
     * MeiliSearchService constructor.
     *
     * @param SerializerInterface $normalizer
     * @param Engine              $engine
     * @param array               $configuration
     */
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

    /**
     * {@inheritdoc}
     */
    public function getSearchable(): array
    {
        return $this->searchableEntities;
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    public function clear(string $className): array
    {
        $this->assertIsSearchable($className);

        return $this->engine->clear($this->searchableAs($className));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $className): ?array
    {
        $this->assertIsSearchable($className);

        return $this->engine->delete($this->searchableAs($className));
    }

    /**
     * {@inheritdoc}
     */
    public function search(
        ObjectManager $objectManager,
        string $className,
        string $query = '',
        array $requestOptions = []
    ): array {
        $this->assertIsSearchable($className);

        $ids = $this->engine->search($query, $this->searchableAs($className), $requestOptions);
        $results = [];
        
        // Check if the engine return results in "hits" key
        if (isset($ids['hits'])) {
            $ids = $ids['hits'];
        }

        foreach ($ids as $objectID) {
            if (in_array($className, $this->aggregators, true)) {
                $entityClass = $className::getEntityClassFromObjectID($objectID);
                $id = $className::getEntityIdFromObjectID($objectID);
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
    public function count(string $className, string $query = '', array $requestOptions = []): int
    {
        $this->assertIsSearchable($className);

        return $this->engine->count($query, $this->searchableAs($className), $requestOptions);
    }

    /**
     * @param object $entity
     *
     * @return bool
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

        foreach ($this->configuration['indices'] as $name => $index) {
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
     * @param ObjectManager      $objectManager
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
     * @param ObjectManager      $objectManager
     * @param array<int, object> $entities
     * @param callable           $operation
     *
     * @return array
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

    /**
     * @param string $className
     *
     * @return bool
     */
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
