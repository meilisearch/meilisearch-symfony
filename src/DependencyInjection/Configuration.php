<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\DependencyInjection;

use Meilisearch\Bundle\Searchable;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('meili_search');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('url')->defaultValue('http://localhost:7700')->end()
                ->scalarNode('api_key')->end()
                ->scalarNode('prefix')
                    ->defaultNull()
                ->end()
                ->integerNode('nbResults')
                    ->defaultValue(20)
                ->end()
                ->integerNode('batchSize')
                    ->defaultValue(500)
                ->end()
                ->arrayNode('doctrineSubscribedEvents')
                    ->prototype('scalar')->end()
                    ->defaultValue(['postPersist', 'postUpdate', 'preRemove'])
                ->end()
                ->scalarNode('serializer')
                    ->defaultValue('serializer')
                ->end()
                ->scalarNode('http_client')
                    ->defaultValue('psr18.http_client')
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
                            ->variableNode('settings')
                                ->defaultValue([])
                                ->info('Configure indices settings, see: https://www.meilisearch.com/docs/reference/api/settings')
                                ->beforeNormalization()
                                    ->always()
                                    ->then(static function ($value) {
                                        if (null === $value) {
                                            return [];
                                        }

                                        if (!\is_array($value)) {
                                            throw new InvalidConfigurationException('Settings must be an array.');
                                        }

                                        $stringSettings = ['distinctAttribute', 'proximityPrecision', 'searchCutoffMs'];

                                        foreach ($stringSettings as $setting) {
                                            if (isset($value[$setting]) && !\is_array($value[$setting])) {
                                                $value[$setting] = (array) $value[$setting];
                                            }
                                        }

                                        return $value;
                                    })
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
