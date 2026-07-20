<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Meilisearch\Bundle\EventListener\AsyncDoctrineEventSubscriber;
use Meilisearch\Bundle\Messenger\IndexEntitiesMessageHandler;
use Meilisearch\Bundle\Messenger\RemoveDocumentsMessageHandler;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('meilisearch.async_search_indexer_subscriber', AsyncDoctrineEventSubscriber::class)
        ->public()
        ->args([
            service('meilisearch.manager'),
            abstract_arg('message bus defined in MeilisearchExtension'),
        ]);

    $services->set('meilisearch.messenger.index_entities_handler', IndexEntitiesMessageHandler::class)
        ->args([
            service('meilisearch.manager'),
            service('doctrine'),
        ])
        ->tag('messenger.message_handler');

    $services->set('meilisearch.messenger.remove_documents_handler', RemoveDocumentsMessageHandler::class)
        ->args([
            service('meilisearch.engine'),
        ])
        ->tag('messenger.message_handler');
};
