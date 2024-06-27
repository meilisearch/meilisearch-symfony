<?php

declare(strict_types=1);

namespace Meilisearch\Bundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class MeilisearchBundle extends Bundle
{
    public const VERSION = '0.15.2';

    public static function qualifiedVersion()
    {
        return sprintf('Meilisearch Symfony (v%s)', MeilisearchBundle::VERSION);
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
