<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\DataProvider;

use Doctrine\Persistence\ManagerRegistry;
use Meilisearch\Bundle\Exception\LogicException;

final class OrmEntityProvider implements DataProviderInterface
{
    /**
     * @param class-string $className
     */
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly string $className,
    ) {
    }

    public function provide(int $limit, int $offset): array
    {
        $manager = $this->managerRegistry->getManagerForClass($this->className);
        $repository = $manager->getRepository($this->className);
        $classMetadata = $manager->getClassMetadata($this->className);
        $entityIdentifiers = $classMetadata->getIdentifierFieldNames();
        $sortByAttrs = array_combine($entityIdentifiers, array_fill(0, \count($entityIdentifiers), 'ASC'));

        return $repository->findBy([], $sortByAttrs, $limit, $offset);
    }

    public function loadByIdentifiers(array $identifiers): array
    {
        $manager = $this->managerRegistry->getManagerForClass($this->className);
        $repository = $manager->getRepository($this->className);
        $classMetadata = $manager->getClassMetadata($this->className);
        $identifierFieldNames = $classMetadata->getIdentifierFieldNames();

        // For single-field identifiers, use the actual field name
        if (1 === \count($identifierFieldNames)) {
            return $repository->findBy([$identifierFieldNames[0] => $identifiers]);
        }

        throw new LogicException('Composite identifiers are not yet supported.');
    }

    public function getIdentifierValues(object $object): array
    {
        $manager = $this->managerRegistry->getManagerForClass(\get_class($object));

        return $manager->getClassMetadata(\get_class($object))->getIdentifierValues($object);
    }

    public function cleanup(): void
    {
        $this->managerRegistry->getManagerForClass($this->className)->clear();
    }
}
