<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\DataProvider;

use Doctrine\Persistence\ManagerRegistry;
use Meilisearch\Bundle\DataProvider\DataProvider;
use Meilisearch\Bundle\Tests\Entity\Ticket;

final class TicketDataProvider implements DataProvider
{
    private ManagerRegistry $managerRegistry;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function getAll(int $limit = 100, int $offset = 0): array
    {
        $manager = $this->managerRegistry->getManagerForClass(Ticket::class);
        $repository = $manager->getRepository(Ticket::class);

        return $repository->findBy(
            ['sold' => false],
            ['id' => 'ASC'],
            $limit,
            $offset
        );
    }
}
