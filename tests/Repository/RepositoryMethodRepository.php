<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Repository;

use Doctrine\ORM\EntityRepository;

class RepositoryMethodRepository extends EntityRepository
{
    public function customRepositoryMethod(int $limit, int $offset): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.isFiltered = :filtered')
            ->setParameter('filtered', true)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }
}
