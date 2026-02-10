<?php

declare(strict_types=1);

namespace Meilisearch\Bundle;

use Meilisearch\Bundle\DependencyInjection\Compiler\DataProviderPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class MeilisearchBundle extends Bundle
{
    public const VERSION = '0.16.1';

    public static function qualifiedVersion(): string
    {
        return \sprintf('Meilisearch Symfony (v%s)', self::VERSION);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new DataProviderPass());
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
