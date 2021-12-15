<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Test\DataProvider;

use MeiliSearch\Bundle\DataProvider\DataProviderRegistry;
use MeiliSearch\Bundle\Test\DataProvider\Assets\TestDataProvider;
use PHPUnit\Framework\TestCase;

final class DataProviderRegistryTest extends TestCase
{
    public function testDataProviderCanBeFiltered(): void
    {
        $dataProviderRegistry = new DataProviderRegistry([
            new TestDataProvider(),
        ]);

        $filteredRegistry = $dataProviderRegistry->filter(static fn (TestDataProvider $dataProvider) => $dataProvider->support('foo'));

        self::assertSame(1, $filteredRegistry->count());
    }
}
