<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Meilisearch\Bundle\DependencyInjection\MeilisearchExtension;
use Meilisearch\Bundle\MeilisearchBundle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

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

    #[IgnoreDeprecations]
    public function testHasMeilisearchVersionFromConstantAfterLoad(): void
    {
        $this->load(['url' => 'http://meilisearch:7700', 'api_key' => null]);

        $this->assertContainerBuilderHasParameter('meili_symfony_version', MeilisearchBundle::qualifiedVersion());
    }

    #[DataProvider('dataTestParametersTheBundleNoLongerUses')]
    #[IgnoreDeprecations]
    public function testReadingAParameterTheBundleNoLongerUsesTriggersADeprecation(string $parameter): void
    {
        $this->load(['url' => 'http://meilisearch:7700', 'api_key' => null]);

        $deprecations = [];
        set_error_handler(static function (int $type, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            $this->container->getParameter($parameter);
        } finally {
            restore_error_handler();
        }

        self::assertSame(
            [\sprintf('Since meilisearch/meilisearch-symfony 0.17: The parameter "%s" is deprecated and will be removed, it is no longer used by the bundle.', $parameter)],
            $deprecations,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function dataTestParametersTheBundleNoLongerUses(): iterable
    {
        yield 'meili_url' => ['meili_url'];
        yield 'meili_api_key' => ['meili_api_key'];
        yield 'meili_symfony_version' => ['meili_symfony_version'];
    }
}
