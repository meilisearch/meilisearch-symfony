<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Meilisearch\Bundle\DependencyInjection\MeilisearchExtension;
use Meilisearch\Bundle\MeilisearchBundle;

final class DependencyInjectionTest extends AbstractExtensionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->container->setParameter('kernel.bundles', []);
    }

    protected function getContainerExtensions(): array
    {
        return [
            new MeilisearchExtension(),
        ];
    }

    public function testHasMeilisearchVersionDefinitionAfterLoad(): void
    {
        $this->load(['url' => 'http://meilisearch:7700', 'api_key' => null]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument('meilisearch.client', 4, [MeilisearchBundle::qualifiedVersion()]);
    }

    public function testHasMeilisearchVersionFromConstantAfterLoad(): void
    {
        $this->load(['url' => 'http://meilisearch:7700', 'api_key' => null]);

        $this->assertContainerBuilderHasParameter('meili_symfony_version', MeilisearchBundle::qualifiedVersion());
    }
}
