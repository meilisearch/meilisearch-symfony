<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Test;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Meilisearch\Bundle\MeilisearchBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as HttpKernel;

/**
 * Class Kernel.
 */
class Kernel extends HttpKernel
{
    /**
     * {@inheritDoc}
     *
     * @return array<int, BundleInterface>
     */
    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new MeilisearchBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__.'/config/config.yaml');
        $loader->load(__DIR__.'/../src/Resources/config/services.xml');
        $loader->load(__DIR__.'/config/meilisearch.yaml');
    }
}
