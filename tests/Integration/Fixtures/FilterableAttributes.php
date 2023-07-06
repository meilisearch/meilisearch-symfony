<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration\Fixtures;

use Meilisearch\Bundle\SettingsProvider;

class FilterableAttributes implements SettingsProvider
{
    public function __invoke(): array
    {
        return ['title', 'publishedAt'];
    }
}
