<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Meilisearch\Bundle\Command\MeilisearchClearCommand;
use Meilisearch\Bundle\Command\MeilisearchCreateCommand;
use Meilisearch\Bundle\Command\MeilisearchDeleteCommand;
use Meilisearch\Bundle\Command\MeilisearchImportCommand;
use Meilisearch\Bundle\Command\MeilisearchUpdateSettingsCommand;
use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\EventListener\DoctrineEventSubscriber;
use Meilisearch\Bundle\SearchService;
use Meilisearch\Bundle\Services\MeilisearchService;
use Meilisearch\Bundle\Services\SettingsUpdater;
use Meilisearch\Bundle\Services\UnixTimestampNormalizer;
use Meilisearch\Client;

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $parameters = $container->parameters();

    $services->set('meilisearch.engine', Engine::class)
        ->args([service('meilisearch.client')]);

    $services->alias(Engine::class, 'meilisearch.engine');

    $services->set('meilisearch.service', MeilisearchService::class)
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

    $services->set('meilisearch.search_indexer_subscriber', DoctrineEventSubscriber::class)
        ->public()
        ->args([service('meilisearch.service')]);

    $services->alias('search.search_indexer_subscriber', 'meilisearch.search_indexer_subscriber')
        ->deprecate('meilisearch/search-bundle', '0.14', 'The "%alias_id%" service alias is deprecated. Use "meilisearch.search_indexer_subscriber" instead.');

    $services->set('meilisearch.client', Client::class)
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

    $services->alias(Client::class, 'meilisearch.client')
        ->public();

    $services->alias(SearchService::class, 'meilisearch.service');

    $services->set('meilisearch.settings_updater', SettingsUpdater::class)
        ->args([
            service('meilisearch.service'),
            service('meilisearch.client'),
            service('event_dispatcher'),
        ]);

    $services->alias(SettingsUpdater::class, 'meilisearch.settings_updater');

    $services->set(MeilisearchClearCommand::class)
        ->args([service('meilisearch.service')])
        ->tag('console.command', ['command' => 'meilisearch:clear|meili:clear', 'description' => 'Clear the index documents']);

    $services->set(MeilisearchCreateCommand::class)
        ->args([
            service('meilisearch.service'),
            service('meilisearch.client'),
            service('meilisearch.settings_updater'),
            service('event_dispatcher'),
        ])
        ->tag('console.command', ['command' => 'meilisearch:create|meili:create', 'description' => 'Create indexes']);

    $services->set(MeilisearchDeleteCommand::class)
        ->args([service('meilisearch.service')])
        ->tag('console.command', ['command' => 'meilisearch:delete|meili:delete', 'description' => 'Delete the indexes']);

    $services->set(MeilisearchImportCommand::class)
        ->args([
            service('meilisearch.service'),
            service('doctrine'),
            service('meilisearch.client'),
            service('meilisearch.settings_updater'),
            service('event_dispatcher'),
        ])
        ->tag('console.command', ['command' => 'meilisearch:import|meili:import', 'description' => 'Import given entity into search engine']);

    $services->set(MeilisearchUpdateSettingsCommand::class)
        ->args([
            service('meilisearch.service'),
            service('meilisearch.settings_updater'),
            service('event_dispatcher'),
        ])
        ->tag('console.command', ['command' => 'meilisearch:update-settings', 'description' => 'Push settings to meilisearch']);

    $services->set(UnixTimestampNormalizer::class)
        ->tag('serializer.normalizer');
};
