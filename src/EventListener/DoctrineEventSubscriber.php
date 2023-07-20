<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\EventListener;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Meilisearch\Bundle\SearchManagerInterface;

final class DoctrineEventSubscriber
{
    public function __construct(private readonly SearchManagerInterface $searchManager)
    {
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->searchManager->index($args->getObject());
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->searchManager->index($args->getObject());
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        $this->searchManager->remove($args->getObject());
    }
}
