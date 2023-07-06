<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\EventListener;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Meilisearch\Bundle\SearchService;

final class DoctrineEventSubscriber
{
    private SearchService $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->searchService->index($args->getObjectManager(), $args->getObject());
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->searchService->index($args->getObjectManager(), $args->getObject());
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        $this->searchService->remove($args->getObjectManager(), $args->getObject());
    }
}
