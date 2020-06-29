<?php

namespace MeiliSearch\Bundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use MeiliSearch\Bundle\SearchService;

/**
 * Class MeiliSearchIndexerSubscriber.
 *
 * @package MeiliSearch\Bundle\EventListener
 */
final class MeiliSearchIndexerSubscriber implements EventSubscriber
{
    /** @var SearchService */
    protected $searchService;

    /** @var array */
    protected $subscribedEvents;

    /**
     * MeiliSearchIndexerSubscriber constructor.
     *
     * @param SearchService $searchService
     * @param array         $subscribedEvents
     */
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
        $this->searchService->remove($args->getObjectManager(), $object = $args->getObject());
    }
}
