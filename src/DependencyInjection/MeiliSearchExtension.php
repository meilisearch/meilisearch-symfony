<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\DependencyInjection;

use MeiliSearch\Bundle\Engine;
use MeiliSearch\Bundle\Services\MeiliSearchService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use function count;
use function dirname;

/**
 * Class MeiliSearchExtension.
 *
 * @package MeiliSearch\Bundle\DependencyInjection
 */
final class MeiliSearchExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new Loader\XmlFileLoader($container, new FileLocator(dirname(__DIR__).'/Resources/config'));
        $loader->load('services.xml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        if (null === $config['prefix']) {
            $config['prefix'] = $container->getParameter('kernel.environment').'_';
        }

        if (count($doctrineSubscribedEvents = $config['doctrineSubscribedEvents']) > 0) {
            $container->getDefinition('search.search_indexer_subscriber')->setArgument(1, $doctrineSubscribedEvents);
        } else {
            $container->removeDefinition('search.search_indexer_subscriber');
        }

        $engineDefinition = new Definition(Engine::class, [new Reference('search.client')]);

        $searchServiceDefinition = (new Definition(
            MeiliSearchService::class,
            [new Reference($config['serializer']), $engineDefinition, $config]
        ));

        $container->setDefinition('search.service', $searchServiceDefinition->setPublic(true));
    }
}
