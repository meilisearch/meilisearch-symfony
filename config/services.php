<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function(ContainerConfigurator $container) {
    $services = $container->services();
    $parameters = $container->parameters();

    $services->set('meilisearch.engine', \Meilisearch\Bundle\Engine::class)
        ->args([service('meilisearch.client')]);

    $services->alias(\Meilisearch\Bundle\Engine::class, 'meilisearch.engine');

    $services->set('meilisearch.service', \Meilisearch\Bundle\Services\MeilisearchService::class)
        ->public()
        ->args([
            abstract_arg('normalizer'),
            service('meilisearch.engine'),
            abstract_arg('configuration'),
            service('property_accessor'),
        ]);

    $services->alias('search.service', 'meilisearch.service')
        ->public()
        ->deprecate('meilisearch/search-bundle', '0.14', 'The "%alias_id%" service alias is deprecated. Use "meilisearch.service" instead.');

    $services->set('meilisearch.search_indexer_subscriber', \Meilisearch\Bundle\EventListener\DoctrineEventSubscriber::class)
        ->public()
        ->args([service('meilisearch.service')]);

    $services->alias('search.search_indexer_subscriber', 'meilisearch.search_indexer_subscriber')
        ->deprecate('meilisearch/search-bundle', '0.14', 'The "%alias_id%" service alias is deprecated. Use "meilisearch.search_indexer_subscriber" instead.');

    $services->set('meilisearch.client', \Meilisearch\Client::class)
        ->public()
        ->lazy()
        ->args([
            abstract_arg('url defined in MeilisearchExtension'),
            abstract_arg('api key defined in MeilisearchExtension'),
            abstract_arg('http client defined in MeilisearchExtension'),
            null,
            abstract_arg('client agents defined in MeilisearchExtension'),
            null,
        ]);

    $services->alias('search.client', 'meilisearch.client')
        ->public()
        ->deprecate('meilisearch/search-bundle', '0.14', 'The "%alias_id%" service alias is deprecated. Use "meilisearch.client" instead.');

    $services->alias(\Meilisearch\Client::class, 'meilisearch.client')
        ->public();

    $services->alias(\Meilisearch\Bundle\SearchService::class, 'meilisearch.service');

    $services->set('meilisearch.settings_updater', \Meilisearch\Bundle\Services\SettingsUpdater::class)
        ->args([
            service('meilisearch.service'),
            service('meilisearch.client'),
            service('event_dispatcher'),
        ]);

    $services->alias(\Meilisearch\Bundle\Services\SettingsUpdater::class, 'meilisearch.settings_updater');

    $services->set(\Meilisearch\Bundle\Command\MeilisearchClearCommand::class)
        ->args([service('meilisearch.service')])
        ->tag('console.command', ['command' => 'meilisearch:clear|meili:clear', 'description' => 'Clear the index documents']);

    $services->set(\Meilisearch\Bundle\Command\MeilisearchCreateCommand::class)
        ->args([
            service('meilisearch.service'),
            service('meilisearch.client'),
            service('meilisearch.settings_updater'),
            service('event_dispatcher'),
        ])
        ->tag('console.command', ['command' => 'meilisearch:create|meili:create', 'description' => 'Create indexes']);

    $services->set(\Meilisearch\Bundle\Command\MeilisearchDeleteCommand::class)
        ->args([service('meilisearch.service')])
        ->tag('console.command', ['command' => 'meilisearch:delete|meili:delete', 'description' => 'Delete the indexes']);

    $services->set(\Meilisearch\Bundle\Command\MeilisearchImportCommand::class)
        ->args([
            service('meilisearch.service'),
            service('doctrine'),
            service('meilisearch.client'),
            service('meilisearch.settings_updater'),
            service('event_dispatcher'),
        ])
        ->tag('console.command', ['command' => 'meilisearch:import|meili:import', 'description' => 'Import given entity into search engine']);

    $services->set(\Meilisearch\Bundle\Command\MeilisearchUpdateSettingsCommand::class)
        ->args([
            service('meilisearch.service'),
            service('meilisearch.settings_updater'),
            service('event_dispatcher'),
        ])
        ->tag('console.command', ['command' => 'meilisearch:update-settings', 'description' => 'Push settings to meilisearch']);

    $services->set(\Meilisearch\Bundle\Services\UnixTimestampNormalizer::class)
        ->tag('serializer.normalizer');
};
