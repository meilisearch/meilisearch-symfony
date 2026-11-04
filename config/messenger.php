<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Meilisearch\Bundle\EventListener\AsyncDoctrineEventSubscriber;
use Meilisearch\Bundle\Messenger\IndexSearchablesMessage;
use Meilisearch\Bundle\Messenger\IndexSearchablesMessageHandler;
use Meilisearch\Bundle\Messenger\RemoveDocumentsMessage;
use Meilisearch\Bundle\Messenger\RemoveDocumentsMessageHandler;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('meilisearch.async_search_indexer_subscriber', AsyncDoctrineEventSubscriber::class)
        ->public()
        ->args([
            service('meilisearch.manager'),
            abstract_arg('message bus defined in MeilisearchExtension'),
            service('meilisearch.data_provider.registry'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set('meilisearch.messenger.index_searchables_handler', IndexSearchablesMessageHandler::class)
        ->args([
            service('meilisearch.manager'),
            service('meilisearch.data_provider.registry'),
        ])
        ->tag('messenger.message_handler', ['handles' => IndexSearchablesMessage::class]);

    $services->set('meilisearch.messenger.remove_documents_handler', RemoveDocumentsMessageHandler::class)
        ->args([
            service('meilisearch.manager'),
            service('meilisearch.engine'),
        ])
        ->tag('messenger.message_handler', ['handles' => RemoveDocumentsMessage::class]);
};
