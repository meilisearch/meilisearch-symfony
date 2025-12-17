<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Meilisearch\Bundle\Command\MeilisearchClearCommand;
use Meilisearch\Bundle\Command\MeilisearchCreateCommand;
use Meilisearch\Bundle\Command\MeilisearchDeleteCommand;
use Meilisearch\Bundle\Command\MeilisearchImportCommand;
use Meilisearch\Bundle\Command\MeilisearchUpdateSettingsCommand;
use Meilisearch\Bundle\DataProvider\DataProviderRegistry;
use Meilisearch\Bundle\DataProvider\DataProviderRegistryInterface;
use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\Identifier\DefaultIdNormalizer;
use Meilisearch\Bundle\Identifier\IdNormalizerInterface;
use Meilisearch\Bundle\SearchManagerInterface;
use Meilisearch\Bundle\SearchService;
use Meilisearch\Bundle\Services\MeilisearchManager;
use Meilisearch\Bundle\Services\MeilisearchService;
use Meilisearch\Bundle\Services\SettingsUpdater;
use Meilisearch\Bundle\Services\UnixTimestampNormalizer;
use Meilisearch\Client;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

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
            service('meilisearch.manager'),
        ]);

    $services->alias('search.service', 'meilisearch.service')
        ->public()
        ->deprecate('meilisearch/search-bundle', '0.14', 'The "%alias_id%" service alias is deprecated. Use "meilisearch.service" instead.');

    $services->alias(SearchService::class, 'meilisearch.service');

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

    $services->set('meilisearch.manager', MeilisearchManager::class)
        ->args([
            abstract_arg('normalizer'),
            service('meilisearch.engine'),
            service('property_accessor'),
            service('meilisearch.data_provider.registry'),
            abstract_arg('configuration'),
        ]);
    $services->alias(SearchManagerInterface::class, 'meilisearch.manager');

    $services->set('meilisearch.settings_updater', SettingsUpdater::class)
        ->args([
            service('meilisearch.manager'),
            service('meilisearch.client'),
            service('event_dispatcher'),
        ]);

    $services->alias(SettingsUpdater::class, 'meilisearch.settings_updater');

    $services->set(MeilisearchClearCommand::class)
        ->args([service('meilisearch.manager')])
        ->tag('console.command', ['command' => 'meilisearch:clear|meili:clear', 'description' => 'Clear the index documents']);

    $services->set(MeilisearchCreateCommand::class)
        ->args([
            service('meilisearch.manager'),
            service('meilisearch.client'),
            service('meilisearch.settings_updater'),
            service('event_dispatcher'),
        ])
        ->tag('console.command', ['command' => 'meilisearch:create|meili:create', 'description' => 'Create indexes']);

    $services->set(MeilisearchDeleteCommand::class)
        ->args([service('meilisearch.manager')])
        ->tag('console.command', ['command' => 'meilisearch:delete|meili:delete', 'description' => 'Delete the indexes']);

    $services->set(MeilisearchImportCommand::class)
        ->args([
            service('meilisearch.manager'),
            service('meilisearch.client'),
            service('meilisearch.settings_updater'),
            service('event_dispatcher'),
            service('meilisearch.data_provider.registry'),
        ])
        ->tag('console.command', ['command' => 'meilisearch:import|meili:import', 'description' => 'Import given entity into search engine']);

    $services->set(MeilisearchUpdateSettingsCommand::class)
        ->args([
            service('meilisearch.manager'),
            service('meilisearch.settings_updater'),
            service('event_dispatcher'),
        ])
        ->tag('console.command', ['command' => 'meilisearch:update-settings', 'description' => 'Push settings to meilisearch']);

    $services->set(UnixTimestampNormalizer::class)
        ->tag('serializer.normalizer');

    $services->set('meilisearch.data_provider.registry', DataProviderRegistry::class)
        ->args([
            abstract_arg('provider locator'),
            abstract_arg('provider map'),
        ])
    ->alias(DataProviderRegistryInterface::class, 'meilisearch.data_provider.registry');

    $services
        ->set('meilisearch.identifier.default_id_normalizer', DefaultIdNormalizer::class)
    ->alias(IdNormalizerInterface::class, 'meilisearch.identifier.default_id_normalizer')

    ;
};
