<?php

declare(strict_types=1);

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Meilisearch\Bundle\DependencyInjection\MeilisearchExtension;
use Meilisearch\Bundle\MeilisearchBundle;

class MeilisearchExtensionTest extends AbstractExtensionTestCase
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

        $this->assertContainerBuilderHasServiceDefinitionWithArgument('meilisearch.client', '$clientAgents', ['%meili_symfony_version%']);
    }

    public function testHasMeilisearchVersionFromConstantAfterLoad(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('meili_symfony_version', MeilisearchBundle::qualifiedVersion());
    }
}
