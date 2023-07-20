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
