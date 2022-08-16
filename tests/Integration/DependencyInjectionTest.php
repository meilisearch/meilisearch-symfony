<?php

declare(strict_types=1);

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use MeiliSearch\Bundle\DependencyInjection\MeiliSearchExtension;
use MeiliSearch\Bundle\MeiliSearchBundle;

class MeiliSearchExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new MeiliSearchExtension(),
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

        $this->assertContainerBuilderHasParameter('meili_symfony_version', MeiliSearchBundle::qualifiedVersion());
    }
}
