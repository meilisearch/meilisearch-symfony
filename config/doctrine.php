<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Meilisearch\Bundle\EventListener\DoctrineEventSubscriber;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('meilisearch.search_indexer_subscriber', DoctrineEventSubscriber::class)
        ->public()
        ->args([
            service('meilisearch.manager')
        ])

    ;
};
