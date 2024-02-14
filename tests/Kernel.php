<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests;

use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\Configuration;
use Meilisearch\Bundle\MeilisearchBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as HttpKernel;

class Kernel extends HttpKernel
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
        if (PHP_VERSION_ID >= 80000) {
            $loader->load(__DIR__.'/config/config.yaml');
        } else {
            $loader->load(__DIR__.'/config/config_php7.yaml');
        }
        $loader->load(__DIR__.'/config/meilisearch.yaml');

        if (defined(ConnectionFactory::class.'::DEFAULT_SCHEME_MAP')) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'report_fields_where_declared' => true,
                ],
            ]);
        }

        // @phpstan-ignore-next-line
        if (method_exists(Configuration::class, 'setLazyGhostObjectEnabled') && Kernel::VERSION_ID >= 60100) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'enable_lazy_ghost_objects' => true,
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
    }
}
