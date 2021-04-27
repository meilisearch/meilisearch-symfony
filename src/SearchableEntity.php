<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle;

use Doctrine\Persistence\Mapping\ClassMetadata;
use JMS\Serializer\ArrayTransformerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Class SearchableEntity.
 */
final class SearchableEntity
{
    private string $indexName;

    /** @var object */
    private $entity;

    private ClassMetadata $entityMetadata;

    /** @var object */
    private $normalizer;

    private bool $useSerializerGroups;

    /** @var int|string */
    private $id;

    /**
     * SearchableEntity constructor.
     *
     * @param object      $entity
     * @param object|null $normalizer
     */
    public function __construct(
        string $indexName,
        $entity,
        ClassMetadata $entityMetadata,
        $normalizer = null,
        array $extra = []
    ) {
        $this->indexName = $indexName;
        $this->entity = $entity;
        $this->entityMetadata = $entityMetadata;
        $this->normalizer = $normalizer;
        $this->useSerializerGroups = isset($extra['useSerializerGroup']) && $extra['useSerializerGroup'];

        $this->setId();
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    /**
     * @throws ExceptionInterface
     */
    public function getSearchableArray(): array
    {
        $context = ['fieldsMapping' => $this->entityMetadata->fieldMappings];

        if ($this->useSerializerGroups) {
            $context['groups'] = [Searchable::NORMALIZATION_GROUP];
        }

        if ($this->normalizer instanceof NormalizerInterface) {
            return $this->normalizer->normalize($this->entity, Searchable::NORMALIZATION_FORMAT, $context);
        } elseif ($this->normalizer instanceof ArrayTransformerInterface) {
            return $this->normalizer->toArray($this->entity);
        }

        return [];
    }

    private function setId(): void
    {
        $ids = $this->entityMetadata->getIdentifierValues($this->entity);

        if (0 === \count($ids)) {
            throw new Exception('Entity has no primary key');
        }

        if (1 == \count($ids)) {
            $this->id = reset($ids);
        } else {
            $objectID = '';
            foreach ($ids as $key => $value) {
                $objectID .= $key.'-'.$value.'__';
            }

            $this->id = rtrim($objectID, '_');
        }
    }

    /**
     * @return int|string
     */
    public function getId()
    {
        return $this->id;
    }
}
