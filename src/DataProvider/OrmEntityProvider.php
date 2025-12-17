<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\DataProvider;

use Doctrine\Persistence\ManagerRegistry;
use Meilisearch\Bundle\Identifier\IdNormalizerInterface;

final class OrmEntityProvider implements DataProviderInterface
{
    /**
     * @param class-string $className
     */
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly IdNormalizerInterface $idNormalizer,
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

        $queryBuilder = $repository->createQueryBuilder('e');
        $expr = $queryBuilder->expr();
        $orX = $expr->orX();
        $paramIndex = 0;

        foreach ($identifiers as $id) {
            $ids = $this->idNormalizer->denormalize($id);

            $andX = $expr->andX();
            foreach ($ids as $key => $value) {
                $param = ":dcValue$paramIndex";
                $andX->add("e.$key = $param");
                $queryBuilder->setParameter($param, $value);
                ++$paramIndex;

                // use this when we'll only support doctrine/orm >= 3.3
                // $andX->add("e.$key = {$queryBuilder->createNamedParameter($value)}");
            }

            $orX->add($andX);
        }

        $queryBuilder->where($orX);

        return $queryBuilder->getQuery()->getResult();
    }

    public function getIdentifierValues(object $object): array
    {
        $manager = $this->managerRegistry->getManagerForClass(\get_class($object));

        return $manager->getClassMetadata(\get_class($object))->getIdentifierValues($object);
    }

    public function normalizeIdentifiers(array $identifiers): string|int
    {
        return $this->idNormalizer->normalize($identifiers);
    }

    public function denormalizeIdentifier(string $identifier): array
    {
        return $this->idNormalizer->denormalize($identifier);
    }

    public function cleanup(): void
    {
        $this->managerRegistry->getManagerForClass($this->className)->clear();
    }
}
