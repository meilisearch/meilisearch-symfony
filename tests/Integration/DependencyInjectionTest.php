<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Meilisearch\Bundle\DependencyInjection\MeilisearchExtension;
use Meilisearch\Bundle\MeilisearchBundle;

class DependencyInjectionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new MeilisearchExtension(),
        ];
    }

    public function testHasMeilisearchVersionDefinitionAfterLoad(): void
    {
        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument('search.client', '$clientAgents', ['%meili_symfony_version%']);
    }

    public function testHasMeilisearchVersionFromConstantAfterLoad(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('meili_symfony_version', MeilisearchBundle::qualifiedVersion());
    }
}
