<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration\DataProvider;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Meilisearch\Bundle\DependencyInjection\MeilisearchExtension;
use Meilisearch\Bundle\Tests\Entity\Movie;

final class OrmEntityProviderRegistrationTest extends AbstractExtensionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->container->setParameter('kernel.bundles', ['DoctrineBundle' => DoctrineBundle::class]);
    }

    protected function getContainerExtensions(): array
    {
        return [
            new MeilisearchExtension(),
        ];
    }

    public function testTheOrmProviderIsResetBetweenRequests(): void
    {
        $this->load([
            'url' => 'http://meilisearch:7700',
            'api_key' => null,
            'indices' => [
                ['name' => 'movies', 'class' => Movie::class],
            ],
        ]);

        // The provider keeps the position of the walk it is in the middle of, so a long-running
        // worker has to be told to drop it between requests and messages.
        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            \sprintf('meilisearch.data_provider.%s_%s', 'movies', hash('xxh32', Movie::class)),
            'kernel.reset',
            ['method' => 'reset'],
        );
    }
}
