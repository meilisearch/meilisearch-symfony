<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration\Fixtures;

use Meilisearch\Bundle\SettingsProvider;

final class StopWords implements SettingsProvider
{
    public function __invoke(): array
    {
        return ['the', 'a', 'n'];
    }
}
