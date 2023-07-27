<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\DependencyInjection;

use Meilisearch\Bundle\DataCollector\MeilisearchDataCollector;
use Meilisearch\Bundle\Debug\TraceableMeilisearchService;
use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\MeilisearchBundle;
use Meilisearch\Bundle\SearchService;
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
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.xml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        if (null === $config['prefix'] && $container->hasParameter('kernel.environment')) {
            $config['prefix'] = $container->getParameter('kernel.environment').'_';
        }

        foreach ($config['indices'] as $index => $indice) {
            $config['indices'][$index]['settings'] = $this->findReferences($config['indices'][$index]['settings']);
        }

        $container->setParameter('meili_url', $config['url'] ?? null);
        $container->setParameter('meili_api_key', $config['api_key'] ?? null);
        $container->setParameter('meili_symfony_version', MeilisearchBundle::qualifiedVersion());

        if (\count($doctrineEvents = $config['doctrineSubscribedEvents']) > 0) {
            $subscriber = $container->getDefinition('meilisearch.search_indexer_subscriber');

            foreach ($doctrineEvents as $event) {
                $subscriber->addTag('doctrine.event_listener', ['event' => $event]);
                $subscriber->addTag('doctrine_mongodb.odm.event_listener', ['event' => $event]);
            }
        } else {
            $container->removeDefinition('meilisearch.search_indexer_subscriber');
        }

        $engineDefinition = new Definition(Engine::class, [new Reference('meilisearch.client')]);

        $searchDefinition = (new Definition(
            MeilisearchService::class,
            [new Reference($config['serializer']), $engineDefinition, $config]
        ));

        $container->setDefinition('meilisearch.service', $searchDefinition->setPublic(true));
        $container->setAlias('search.service', 'meilisearch.service')->setPublic(true);

        if ($container->getParameter('kernel.debug')) {
            $container->register('debug.meilisearch.service', TraceableMeilisearchService::class)
                ->setDecoratedService(SearchService::class)
                ->addArgument(new Reference('debug.meilisearch.service.inner'))
                ->addArgument(new Reference('debug.stopwatch'))
            ;
            $container->register('data_collector.meilisearch', MeilisearchDataCollector::class)
                ->addArgument(new Reference('debug.meilisearch.service'))
                ->addTag('data_collector', [
                    'id' => 'meilisearch',
                    'template' => '@Meilisearch/DataCollector/meilisearch.html.twig',
                ]);
        }
    }

    /**
     * @param array<mixed, mixed> $settings
     *
     * @return array<mixed, mixed>
     */
    private function findReferences(array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                $settings[$key] = $this->findReferences($value);
            } elseif ('_service' === substr((string) $key, -8) || str_starts_with((string) $value, '@') || 'service' === $key) {
                $settings[$key] = new Reference(ltrim($value, '@'));
            }
        }

        return $settings;
    }
}
