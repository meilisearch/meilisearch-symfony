<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\DependencyInjection;

use Meilisearch\Bundle\Searchable;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('meili_search');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('url')->end()
                ->scalarNode('api_key')->end()
                ->scalarNode('prefix')
                    ->defaultValue(null)
                ->end()
                ->scalarNode('nbResults')
                    ->defaultValue(20)
                ->end()
                ->scalarNode('batchSize')
                    ->defaultValue(500)
                ->end()
                ->arrayNode('doctrineSubscribedEvents')
                    ->prototype('scalar')->end()
                    ->defaultValue(['postPersist', 'postUpdate', 'preRemove'])
                ->end()
                ->scalarNode('serializer')
                    ->defaultValue('serializer')
                ->end()
                ->arrayNode('indices')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('class')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->booleanNode('enable_serializer_groups')
                                ->info('When set to true, it will call normalize method with an extra groups parameter "groups" => [Searchable::NORMALIZATION_GROUP]')
                                ->defaultFalse()
                            ->end()
                            ->arrayNode('serializer_groups')
                                ->info('When setting a different value, normalization will be called with it instead of "Searchable::NORMALIZATION_GROUP".')
                                ->defaultValue([Searchable::NORMALIZATION_GROUP])
                                ->scalarPrototype()->end()
                            ->end()
                            ->scalarNode('index_if')
                                ->info('Property accessor path (like method or property name) used to decide if an entry should be indexed.')
                                ->defaultNull()
                            ->end()
                            ->arrayNode('settings')
                                ->info('Configure indices settings, see: https://www.meilisearch.com/docs/reference/api/settings')
                                ->arrayPrototype()
                                    ->variablePrototype()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
