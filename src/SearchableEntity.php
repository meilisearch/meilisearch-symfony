<?php

namespace MeiliSearch\Bundle;

use Doctrine\Persistence\Mapping\ClassMetadata;
use JMS\Serializer\ArrayTransformerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use function count;

/**
 * Class SearchableEntity.
 *
 * @package MeiliSearch\Bundle
 */
final class SearchableEntity
{
    /** @var string */
    protected $indexUid;

    /** @var object */
    protected $entity;

    /** @var ClassMetadata */
    protected $entityMetadata;

    /** @var object */
    protected $normalizer;

    /** @var bool */
    protected $useSerializerGroups;

    /** @var int|string */
    protected $id;

    /**
     * SearchableEntity constructor.
     *
     * @param string        $indexUid
     * @param object        $entity
     * @param ClassMetadata $entityMetadata
     * @param object|null   $normalizer
     * @param array         $extra
     */
    public function __construct(
        string $indexUid,
        $entity,
        ClassMetadata $entityMetadata,
        $normalizer = null,
        array $extra = []
    ) {
        $this->indexUid = $indexUid;
        $this->entity = $entity;
        $this->entityMetadata = $entityMetadata;
        $this->normalizer = $normalizer;
        $this->useSerializerGroups = isset($extra['useSerializerGroup']) && $extra['useSerializerGroup'];

        $this->setId();
    }

    /**
     * @return string
     */
    public function getindexUid(): string
    {
        return $this->indexUid;
    }

    /**
     * @return array
     *
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

    /**
     * @return void
     */
    private function setId()
    {
        $ids = $this->entityMetadata->getIdentifierValues($this->entity);

        if (0 === count($ids)) {
            throw new Exception('Entity has no primary key');
        }

        if (1 == count($ids)) {
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
