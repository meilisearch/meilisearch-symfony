<?php

declare(strict_types=1);

namespace Meilisearch\Bundle;

use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class SearchableEntity
{
    private string $indexUid;

    /** @var object */
    private $entity;

    /** @var ClassMetadata<object> */
    private ClassMetadata $entityMetadata;

    private ?NormalizerInterface $normalizer;

    /**
     * @var list<string>
     */
    private array $normalizationGroups;

    /** @var int|string */
    private $id;

    /**
     * @param object                $entity
     * @param ClassMetadata<object> $entityMetadata
     */
    public function __construct(
        string $indexUid,
        $entity,
        ClassMetadata $entityMetadata,
        NormalizerInterface $normalizer = null,
        array $extra = []
    ) {
        $this->indexUid = $indexUid;
        $this->entity = $entity;
        $this->entityMetadata = $entityMetadata;
        $this->normalizer = $normalizer;
        $this->normalizationGroups = $extra['normalizationGroups'] ?? [];

        $this->setId();
    }

    public function getIndexUid(): string
    {
        return $this->indexUid;
    }

    /**
     * @throws ExceptionInterface
     */
    public function getSearchableArray(): array
    {
        $context = [
            'meilisearch' => true,
            'fieldsMapping' => $this->entityMetadata->fieldMappings,
        ];

        if (count($this->normalizationGroups) > 0) {
            $context['groups'] = $this->normalizationGroups;
        }

        if ($this->entity instanceof NormalizableInterface && null !== $this->normalizer) {
            return $this->entity->normalize($this->normalizer, Searchable::NORMALIZATION_FORMAT, $context);
        }

        if (null !== $this->normalizer) {
            return $this->normalizer->normalize($this->entity, Searchable::NORMALIZATION_FORMAT, $context);
        }

        return [];
    }

    private function setId(): void
    {
        $ids = $this->entityMetadata->getIdentifierValues($this->entity);

        if (0 === \count($ids)) {
            throw new Exception('Entity has no primary key');
        }

        if (1 === \count($ids)) {
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
