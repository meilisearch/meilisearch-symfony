<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests;

use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\Bundle\DoctrineBundle\Dbal\BlacklistSchemaAssetFilter;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\Mapping\LegacyReflectionFields;
use Meilisearch\Bundle\MeilisearchBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as HttpKernel;

/**
 * Test kernel that enables the optional Messenger integration and routes both
 * messages to an in-process (sync) transport, so integration tests can exercise
 * the async opt-in path end-to-end against a real Meilisearch instance.
 */
final class MessengerKernel extends HttpKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new MeilisearchBundle();
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/messenger_'.$this->environment;
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $loader->load(__DIR__.'/config/framework.yaml');

        $doctrineBundleV3 = !class_exists(BlacklistSchemaAssetFilter::class);

        if ($doctrineBundleV3) {
            $loader->load(__DIR__.'/config/doctrine.yaml');
        } elseif (class_exists(LegacyReflectionFields::class) && PHP_VERSION_ID >= 80400) {
            $loader->load(__DIR__.'/config/doctrine_v2.yaml');
        } else {
            $loader->load(__DIR__.'/config/doctrine_old_proxy.yaml');
        }

        $loader->load(__DIR__.'/config/meilisearch.yaml');
        $loader->load(__DIR__.'/config/messenger.yaml');

        if (\defined(ConnectionFactory::class.'::DEFAULT_SCHEME_MAP') && !$doctrineBundleV3) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'report_fields_where_declared' => true,
                    'validate_xml_mapping' => true,
                ],
            ]);
        }

        if (HttpKernel::VERSION_ID >= 70300) {
            $container->prependExtensionConfig('framework', [
                'property_info' => ['with_constructor_extractor' => false],
            ]);
        }
    }
}
