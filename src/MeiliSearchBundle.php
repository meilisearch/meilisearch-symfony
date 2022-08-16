<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class MeiliSearchBundle.
 */
final class MeiliSearchBundle extends Bundle
{
    public const VERSION = '0.8.0';

    public static function qualifiedVersion()
    {
        return sprintf('Meilisearch Symfony (v%s)', MeiliSearchBundle::VERSION);
    }
}
