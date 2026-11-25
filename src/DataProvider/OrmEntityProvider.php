<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\DataProvider;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Meilisearch\Bundle\Identifier\IdNormalizerInterface;
use Symfony\Contracts\Service\ResetInterface;

final class OrmEntityProvider implements DataProviderInterface, ResetInterface
{
    /**
     * Identifier types `>` can compare in the same order the results are sorted in.
     */
    private const SEEKABLE_IDENTIFIER_TYPES = ['integer', 'smallint', 'bigint', 'string', 'guid'];

    private int|string|null $lastIdentifier = null;

    private int $nextOffset = 0;

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

        if (0 === $offset) {
            $this->lastIdentifier = null;
            $this->nextOffset = 0;
        }

        $seekableIdentifier = $this->seekableIdentifier($classMetadata, $entityIdentifiers);

        // Seeking past the rows already handed out keeps the pages stable while the import runs
        // and spares the database the scan a growing OFFSET costs. It needs the caller to be
        // walking the rows in order, which is what importing does.
        if (null !== $seekableIdentifier && $offset === $this->nextOffset) {
            $queryBuilder = $repository->createQueryBuilder('e')
                ->orderBy('e.'.$seekableIdentifier, 'ASC')
                ->setMaxResults($limit);

            if (null !== $this->lastIdentifier) {
                $queryBuilder
                    ->where('e.'.$seekableIdentifier.' > :lastIdentifier')
                    ->setParameter('lastIdentifier', $this->lastIdentifier);
            }

            $entities = $queryBuilder->getQuery()->getResult();
        } else {
            $entities = $repository->findBy([], $sortByAttrs, $limit, $offset);
        }

        // An empty page continues nothing: leaving the state untouched keeps a repeat of the
        // same request answering from the same place.
        if ([] === $entities) {
            return $entities;
        }

        $this->nextOffset = $offset + \count($entities);

        if (null !== $seekableIdentifier) {
            $identifier = $classMetadata->getIdentifierValues($entities[array_key_last($entities)])[$seekableIdentifier];
            $this->lastIdentifier = \is_int($identifier) ? $identifier : (string) $identifier;
        }

        return $entities;
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     * @param list<string>          $entityIdentifiers
     */
    private function seekableIdentifier(ClassMetadata $classMetadata, array $entityIdentifiers): ?string
    {
        if (1 !== \count($entityIdentifiers)) {
            return null;
        }

        $identifier = $entityIdentifiers[0];

        if (!\in_array($classMetadata->getTypeOfField($identifier), self::SEEKABLE_IDENTIFIER_TYPES, true)) {
            return null;
        }

        return $identifier;
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
        $manager = $this->managerRegistry->getManagerForClass($object::class);

        return $manager->getClassMetadata($object::class)->getIdentifierValues($object);
    }

    public function normalizeIdentifiers(array $identifiers): string|int
    {
        return $this->idNormalizer->normalize($identifiers);
    }

    public function denormalizeIdentifier(string $identifier): array
    {
        return $this->idNormalizer->denormalize($identifier);
    }

    /**
     * The position of the walk in progress belongs to that walk alone, so a long-running worker
     * drops it between requests and between messages.
     */
    public function reset(): void
    {
        $this->lastIdentifier = null;
        $this->nextOffset = 0;
    }

    public function cleanup(): void
    {
        $this->managerRegistry->getManagerForClass($this->className)->clear();
    }
}
