<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\DependencyInjection;

use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\MeilisearchBundle;
use Meilisearch\Bundle\Services\MeilisearchService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Class MeilisearchExtension.
 */
final class MeilisearchExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.xml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        if (null === $config['prefix'] && $container->hasParameter('kernel.environment')) {
            $config['prefix'] = $container->getParameter('kernel.environment').'_';
        }

        $container->setParameter('meili_url', $config['url'] ?? null);
        $container->setParameter('meili_api_key', $config['api_key'] ?? null);
        $container->setParameter('meili_symfony_version', MeilisearchBundle::qualifiedVersion());

        if (\count($doctrineEvents = $config['doctrineSubscribedEvents']) > 0) {
            $container->getDefinition('search.search_indexer_subscriber')->setArgument(1, $doctrineEvents);
        } else {
            $container->removeDefinition('search.search_indexer_subscriber');
        }

        $engineDefinition = new Definition(Engine::class, [new Reference('search.client')]);

        $searchDefinition = (new Definition(
            MeilisearchService::class,
            [new Reference($config['serializer']), $engineDefinition, $config]
        ));

        $container->setDefinition('search.service', $searchDefinition->setPublic(true));
    }
}
