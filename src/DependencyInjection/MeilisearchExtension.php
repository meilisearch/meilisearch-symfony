<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\DependencyInjection;

use Meilisearch\Bundle\DataProvider\OrmEntityProvider;
use Meilisearch\Bundle\MeilisearchBundle;
use Meilisearch\Bundle\Model\Aggregator;
use Meilisearch\Bundle\Services\UnixTimestampNormalizer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;

final class MeilisearchExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new Loader\PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        if (null === $config['prefix'] && $container->hasParameter('kernel.environment')) {
            $config['prefix'] = $container->getParameter('kernel.environment').'_';
        }

        $container->setParameter('meili_url', $config['url'] ?? null);
        $container->setParameter('meili_api_key', $config['api_key'] ?? null);
        $container->setParameter('meili_symfony_version', MeilisearchBundle::qualifiedVersion());

        foreach ($config['indices'] as $index => $indice) {
            $config['indices'][$index]['prefixed_name'] = $config['prefix'].$indice['name'];
            $config['indices'][$index]['settings'] = $this->findReferences($config['indices'][$index]['settings']);
        }

        $doctrineEnabled = \array_key_exists('DoctrineBundle', $container->getParameter('kernel.bundles'));

        if ($doctrineEnabled) {
            $loader->load('doctrine.php');

            if (\count($doctrineEvents = $config['doctrineSubscribedEvents']) > 0) {
                $subscriber = $container->getDefinition('meilisearch.search_indexer_subscriber');

                foreach ($doctrineEvents as $event) {
                    $subscriber->addTag('doctrine.event_listener', ['event' => $event]);
                    $subscriber->addTag('doctrine_mongodb.odm.event_listener', ['event' => $event]);
                }
            } else {
                $container->removeDefinition('meilisearch.search_indexer_subscriber');
            }
        }

        $this->registerDataProviders($container, $config, $doctrineEnabled);

        $container->findDefinition('meilisearch.client')
            ->replaceArgument(0, $config['url'])
            ->replaceArgument(1, $config['api_key'])
            ->replaceArgument(2, new Reference($config['http_client'], ContainerInterface::IGNORE_ON_INVALID_REFERENCE))
            ->replaceArgument(4, [MeilisearchBundle::qualifiedVersion()]);

        $container->findDefinition('meilisearch.service')
            ->replaceArgument(0, new Reference($config['serializer']))
            ->replaceArgument(2, $config);

        $container->findDefinition('meilisearch.manager')
            ->replaceArgument(0, new Reference($config['serializer']))
            ->replaceArgument(4, $config);

        if (Kernel::VERSION_ID >= 70100) {
            $container->removeDefinition(UnixTimestampNormalizer::class);
        }
    }

    /**
     * @param array<mixed> $settings
     *
     * @return array<mixed>
     */
    private function findReferences(array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (\is_array($value)) {
                $settings[$key] = $this->findReferences($value);
            } elseif ('_service' === substr((string) $key, -8) || str_starts_with((string) $value, '@') || 'service' === $key) {
                $settings[$key] = new Reference(ltrim($value, '@'));
            }
        }

        return $settings;
    }

    private function registerDataProviders(ContainerBuilder $container, array $config, bool $doctrineEnabled): void
    {
        foreach ($config['indices'] as $indice) {
            $indexName = $indice['name'];
            $class = $indice['class'];
            $idNormalizer = $indice['id_normalizer'];

            if (null !== $indice['data_provider']) {
                if ($container->hasDefinition($indice['data_provider'])) {
                    $container
                        ->findDefinition($indice['data_provider'])
                        ->addTag('meilisearch.data_provider', [
                            'index' => $indexName,
                            'class' => $class,
                        ]);
                }

                continue;
            }

            if ('orm' === $indice['type']) {
                if (!$doctrineEnabled) {
                    throw new \LogicException(\sprintf('Cannot use "ORM" type for index "%s" because `doctrine/doctrine-bundle` bundle is not installed.', $indexName));
                }

                if (is_subclass_of($class, Aggregator::class)) {
                    foreach ($class::getEntities() as $aggregatedClass) {
                        $this->registerOrmProvider($container, $indexName, $aggregatedClass, $idNormalizer);
                    }
                } else {
                    $this->registerOrmProvider($container, $indexName, $class, $idNormalizer);
                }
            }
        }
    }

    private function registerOrmProvider(ContainerBuilder $container, string $indexName, string $class, string $idNormalizer): void
    {
        $definitionId = \sprintf('meilisearch.data_provider.%s_%s', $indexName, hash('xxh32', $class));

        $definition = new Definition(OrmEntityProvider::class, [
            new Reference('doctrine'),
            new Reference($idNormalizer),
            $class,
        ]);

        $definition->addTag('meilisearch.data_provider', [
            'index' => $indexName,
            'class' => $class,
        ]);

        $container->setDefinition($definitionId, $definition);
    }
}
