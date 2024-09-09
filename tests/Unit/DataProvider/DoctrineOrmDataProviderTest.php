<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit\DataProvider;

use Meilisearch\Bundle\DataProvider\DoctrineOrmDataProvider;
use Meilisearch\Bundle\Tests\BaseKernelTestCase;

/**
 * Class ConfigurationTest.
 */
class DoctrineOrmDataProviderTest extends BaseKernelTestCase
{
    public function testDefaultDataProviderWithoutEntityClassName(): void
    {
        $dataProvider = new DoctrineOrmDataProvider($this->get('doctrine'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No entity class name set on data provider.');

        $dataProvider->getAll();
    }
}
