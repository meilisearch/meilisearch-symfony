<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration\Fixtures;

use Meilisearch\Bundle\SettingsProvider;

final class Synonyms implements SettingsProvider
{
    public function __invoke(): array
    {
        return [
            'great' => ['fantastic'],
            'fantastic' => ['great'],
        ];
    }
}
