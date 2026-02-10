<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class DataProviderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('meilisearch.data_provider.registry')) {
            return;
        }

        if ($container->hasParameter('.meilisearch.custom_data_providers')) {
            $customProviders = $container->getParameter('.meilisearch.custom_data_providers');

            foreach ($customProviders as $serviceId => $tagsConfigs) {
                if (!$container->has($serviceId)) {
                    $indices = array_column($tagsConfigs, 'index');

                    throw new \InvalidArgumentException(\sprintf('The service "%s" configured as a "data_provider" for index(es) "%s" was not found.', $serviceId, implode('", "', $indices)));
                }

                $definition = $container->findDefinition($serviceId);

                foreach ($tagsConfigs as $attributes) {
                    $definition->addTag('meilisearch.data_provider', $attributes);
                }
            }
        }

        $definition = $container->getDefinition('meilisearch.data_provider.registry');

        $locatorServices = [];
        $providersMap = [];

        foreach ($container->findTaggedServiceIds('meilisearch.data_provider') as $id => $tags) {
            foreach ($tags as $attributes) {
                $index = $attributes['index'];
                $class = $attributes['class'];

                $locatorKey = $index.'|'.$class;

                $locatorServices[$locatorKey] = new Reference($id);
                $providersMap[$index][$class] = $locatorKey;
            }
        }

        $definition->setArgument('$dataProviders', ServiceLocatorTagPass::register($container, $locatorServices));
        $definition->setArgument('$dataProvidersMap', $providersMap);
    }
}
