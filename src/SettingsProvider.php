<?php

declare(strict_types=1);

namespace Meilisearch\Bundle;

interface SettingsProvider
{
    /**
     * @return array<mixed>
     */
    public function __invoke(): array;
}
