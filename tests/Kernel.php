<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests;

use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\Bundle\DoctrineBundle\Dbal\BlacklistSchemaAssetFilter;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\Mapping\LegacyReflectionFields;
use Meilisearch\Bundle\MeilisearchBundle;
use Symfony\Bridge\Doctrine\ArgumentResolver\EntityValueResolver;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as HttpKernel;

final class Kernel extends HttpKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new MeilisearchBundle();
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $loader->load(__DIR__.'/config/framework.yaml');

        $doctrineBundleV3 = !class_exists(BlacklistSchemaAssetFilter::class);

        if (PHP_VERSION_ID >= 80000) {
            if ($doctrineBundleV3) {
                $loader->load(__DIR__.'/config/doctrine.yaml');
            } elseif (class_exists(LegacyReflectionFields::class) && PHP_VERSION_ID >= 80400) {
                $loader->load(__DIR__.'/config/doctrine_v2.yaml');
            } else {
                $loader->load(__DIR__.'/config/doctrine_old_proxy.yaml');
            }
        } else {
            $container->prependExtensionConfig('framework', [
                'annotations' => true,
                'serializer' => ['enable_annotations' => true],
                'router' => ['utf8' => true],
            ]);

            $loader->load(__DIR__.'/config/doctrine_php7.yaml');
        }
        $loader->load(__DIR__.'/config/meilisearch.yaml');

        if (\defined(ConnectionFactory::class.'::DEFAULT_SCHEME_MAP') && !$doctrineBundleV3) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'report_fields_where_declared' => true,
                    'validate_xml_mapping' => true,
                ],
            ]);
        }

        if (class_exists(EntityValueResolver::class)) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'controller_resolver' => [
                        'auto_mapping' => false,
                    ],
                ],
            ]);
        }

        // @phpstan-ignore-next-line
        if (Kernel::VERSION_ID >= 60400) {
            $container->prependExtensionConfig('framework', [
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
            ]);
        }
        // @phpstan-ignore-next-line
        if (Kernel::VERSION_ID >= 70300) {
            $container->prependExtensionConfig('framework', [
                'property_info' => ['with_constructor_extractor' => false],
            ]);
        }
    }
}
