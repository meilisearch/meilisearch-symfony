<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\DataProvider;

use Doctrine\Persistence\ManagerRegistry;
use MeiliSearch\Bundle\SearchService;
use MeiliSearch\Client;

final class EntityDataProvider implements DataProviderInterface
{
    protected Client $searchClient;
    private ManagerRegistry $registry;
    protected SearchService $searchService;

    public function provide(): array
    {
        // TODO: Implement provide() method.
    }

    public function getIndex(): string
    {

    }

    public function support(string $type): bool
    {
        return 0 === strpos($type, 'doctrine') || 0 === strpos($type, 'entity');
    }
}
