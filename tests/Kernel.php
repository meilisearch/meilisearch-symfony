<?php

namespace MeiliSearch\Bundle\Test;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use MeiliSearch\Bundle\MeiliSearchBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel as HttpKernel;

/**
 * Class Kernel.
 *
 * @package MeiliSearch\Bundle
 */
class Kernel extends HttpKernel
{
    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new MeiliSearchBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__.'/config/config.yaml');
        $loader->load(__DIR__.'/../src/Resources/config/services.xml');
        $loader->load(__DIR__.'/config/meili_search.yaml');
    }
}
