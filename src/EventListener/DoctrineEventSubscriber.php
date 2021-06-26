<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use MeiliSearch\Bundle\SearchService;

final class DoctrineEventSubscriber implements EventSubscriber
{
    private SearchService $searchService;
    private array $subscribedEvents;

    public function __construct(SearchService $searchService, array $subscribedEvents)
    {
        $this->searchService = $searchService;
        $this->subscribedEvents = $subscribedEvents;
    }

    public function getSubscribedEvents(): array
    {
        return $this->subscribedEvents;
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
