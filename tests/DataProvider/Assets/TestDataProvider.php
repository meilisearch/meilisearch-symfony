<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Test\DataProvider\Assets;

use MeiliSearch\Bundle\DataProvider\DataProviderInterface;

final class TestDataProvider implements DataProviderInterface
{
    public function provide(): array
    {
        return [];
    }

    public function getIndex(): string
    {
        return 'foo';
    }

    public function support(string $index): string
    {
        // TODO: Implement support() method.
    }
}
