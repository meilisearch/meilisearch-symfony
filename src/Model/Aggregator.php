<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Model;

use MeiliSearch\Bundle\Exception\EntityNotFoundInObjectID;
use MeiliSearch\Bundle\Exception\InvalidEntityForAggregator;
use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Class Aggregator.
 *
 * @package MeiliSearch\Bundle\Model
 */
abstract class Aggregator implements NormalizableInterface
{
    /**
     * Holds the ObjectID.
     * Typically also contains information concerning the
     * entity class name, and concerning the entity id.
     */
    protected string $objectID;

    /**
     * Holds a doctrine {@ORM\Entity} or {@ODM\Document} object.
     *
     * @var object
     */
    protected $entity;

    /**
     * Aggregator constructor.
     *
     * @param object $entity
     * @param array  $entityIdentifierValues
     */
    public function __construct($entity, array $entityIdentifierValues)
    {
        $this->entity = $entity;

        if (count($entityIdentifierValues) > 1) {
            throw new InvalidEntityForAggregator("Aggregators don't support more than one primary key.");
        }

        $this->objectID = (string) reset($entityIdentifierValues);
    }

    /**
     * Returns the entities class names that should be aggregated.
     *
     * @return string[]
     */
    public static function getEntities(): array
    {
        return [];
    }

    /**
     * Returns an entity id from the provided object id.
     */
    public static function getEntityIdFromObjectId(string $objectId): string
    {
        return $objectId;
    }

    /**
     * Returns an entity class name from the provided object id.
     *
     * @throws EntityNotFoundInObjectID
     */
    public static function getEntityClassFromObjectID(string $objectId): string
    {
        $type = explode('::', $objectId)[0];

        if (in_array($type, static::getEntities(), true)) {
            return $type;
        }

        throw new EntityNotFoundInObjectID("Entity class from ObjectID $objectId not found.");
    }

    /**
     * {@inheritdoc}
     */
    public function normalize(NormalizerInterface $normalizer, ?string $format = null, array $context = []): array
    {
        return array_merge(['objectID' => $this->objectID], $normalizer->normalize($this->entity, $format, $context));
    }
}
