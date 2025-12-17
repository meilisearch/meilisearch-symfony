<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\DependencyInjection;

use Meilisearch\Bundle\SearchableObject;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('meilisearch');
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
                            ->enumNode('type')
                                ->defaultValue('orm')
                                ->values(['orm', 'custom'])
                            ->end()
                            ->scalarNode('primary_key')
                                ->defaultValue('objectID')
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('data_provider')->defaultNull()->end()
                            ->scalarNode('id_normalizer')->defaultValue('meilisearch.identifier.default_id_normalizer')->end()
                            ->booleanNode('enable_serializer_groups')
                                ->info('When set to true, it will call normalize method with an extra groups parameter "groups" => [SearchableObject::NORMALIZATION_GROUP]')
                                ->defaultFalse()
                            ->end()
                            ->arrayNode('serializer_groups')
                                ->info('When setting a different value, normalization will be called with it instead of "SearchableObject::NORMALIZATION_GROUP".')
                                ->defaultValue([SearchableObject::NORMALIZATION_GROUP])
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
                        ->validate()
                            ->ifTrue(static fn (array $v): bool => 'custom' === ($v['type'] ?? null) && null === ($v['data_provider'] ?? null))
                            ->thenInvalid('When index "type" is set to "custom", "data_provider" must be configured.')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
