<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\DataProvider;

use Doctrine\Persistence\ManagerRegistry;

final class DoctrineOrmDataProvider implements DataProvider
{
    private string $entityClassName;
    private ManagerRegistry $managerRegistry;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function setEntityClassName(string $entityClassName): void
    {
        $this->entityClassName = $entityClassName;
    }

    public function getAll(int $limit = 100, int $offset = 0): array
    {
        if (empty($this->entityClassName)) {
            throw new \Exception('No entity class name set on data provider.');
        }

        $manager = $this->managerRegistry->getManagerForClass($this->entityClassName);
        $classMetadata = $manager->getClassMetadata($this->entityClassName);
        $entityIdentifiers = $classMetadata->getIdentifierFieldNames();
        $repository = $manager->getRepository($this->entityClassName);
        $sortByAttrs = array_combine($entityIdentifiers, array_fill(0, \count($entityIdentifiers), 'ASC'));

        return $repository->findBy(
            [],
            $sortByAttrs,
            $limit,
            $offset
        );
    }
}
