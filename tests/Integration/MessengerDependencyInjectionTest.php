<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Meilisearch\Bundle\DependencyInjection\MeilisearchExtension;
use Meilisearch\Bundle\Messenger\IndexEntitiesMessage;
use Meilisearch\Bundle\Messenger\RemoveDocumentsMessage;
use Symfony\Component\DependencyInjection\Reference;

final class MessengerDependencyInjectionTest extends AbstractExtensionTestCase
{
    private const ASYNC_SUBSCRIBER = 'meilisearch.async_search_indexer_subscriber';
    private const SYNC_SUBSCRIBER = 'meilisearch.search_indexer_subscriber';
    private const INDEX_HANDLER = 'meilisearch.messenger.index_entities_handler';
    private const REMOVE_HANDLER = 'meilisearch.messenger.remove_documents_handler';

    protected function getContainerExtensions(): array
    {
        return [
            new MeilisearchExtension(),
        ];
    }

    public function testMessengerDisabledKeepsSynchronousSubscriber(): void
    {
        $this->enableDoctrine();
        $this->load(['url' => 'http://meilisearch:7700', 'api_key' => null]);

        $this->assertContainerBuilderHasService(self::SYNC_SUBSCRIBER);
        $this->assertContainerBuilderNotHasService(self::ASYNC_SUBSCRIBER);
        $this->assertContainerBuilderNotHasService(self::INDEX_HANDLER);
        $this->assertContainerBuilderNotHasService(self::REMOVE_HANDLER);
    }

    public function testMessengerEnabledReplacesSubscriberAndRegistersHandlers(): void
    {
        $this->enableDoctrine();
        $this->load([
            'url' => 'http://meilisearch:7700',
            'api_key' => null,
            'messenger' => ['enabled' => true],
        ]);

        // The synchronous subscriber is fully replaced by the asynchronous one.
        $this->assertContainerBuilderNotHasService(self::SYNC_SUBSCRIBER);
        $this->assertContainerBuilderHasService(self::ASYNC_SUBSCRIBER);

        foreach (['postPersist', 'postUpdate', 'preRemove', 'postFlush'] as $event) {
            $this->assertContainerBuilderHasServiceDefinitionWithTag(self::ASYNC_SUBSCRIBER, 'doctrine.event_listener', ['event' => $event]);
            $this->assertContainerBuilderHasServiceDefinitionWithTag(self::ASYNC_SUBSCRIBER, 'doctrine_mongodb.odm.event_listener', ['event' => $event]);
        }

        $this->assertContainerBuilderHasServiceDefinitionWithTag(self::INDEX_HANDLER, 'messenger.message_handler', ['handles' => IndexEntitiesMessage::class]);
        $this->assertContainerBuilderHasServiceDefinitionWithTag(self::REMOVE_HANDLER, 'messenger.message_handler', ['handles' => RemoveDocumentsMessage::class]);

        // Objects are reloaded through the data providers, not through Doctrine directly.
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(self::INDEX_HANDLER, 1, new Reference('meilisearch.data_provider.registry'));

        // Default bus is referenced when none is configured.
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(self::ASYNC_SUBSCRIBER, 1, new Reference('messenger.default_bus'));
    }

    public function testMessengerEnabledWithCustomBus(): void
    {
        $this->enableDoctrine();
        $this->load([
            'url' => 'http://meilisearch:7700',
            'api_key' => null,
            'messenger' => ['enabled' => true, 'bus' => 'app.custom_bus'],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(self::ASYNC_SUBSCRIBER, 1, new Reference('app.custom_bus'));
        $this->assertContainerBuilderHasServiceDefinitionWithTag(self::INDEX_HANDLER, 'messenger.message_handler', ['handles' => IndexEntitiesMessage::class, 'bus' => 'app.custom_bus']);
        $this->assertContainerBuilderHasServiceDefinitionWithTag(self::REMOVE_HANDLER, 'messenger.message_handler', ['handles' => RemoveDocumentsMessage::class, 'bus' => 'app.custom_bus']);
    }

    public function testMessengerEnabledWithoutDoctrineOnlyRegistersHandlers(): void
    {
        $this->container->setParameter('kernel.bundles', []);
        $this->load([
            'url' => 'http://meilisearch:7700',
            'api_key' => null,
            'messenger' => ['enabled' => true],
        ]);

        $this->assertContainerBuilderNotHasService(self::ASYNC_SUBSCRIBER);
        $this->assertContainerBuilderHasServiceDefinitionWithTag(self::INDEX_HANDLER, 'messenger.message_handler', ['handles' => IndexEntitiesMessage::class]);
        $this->assertContainerBuilderHasServiceDefinitionWithTag(self::REMOVE_HANDLER, 'messenger.message_handler', ['handles' => RemoveDocumentsMessage::class]);
    }

    private function enableDoctrine(): void
    {
        $this->container->setParameter('kernel.bundles', ['DoctrineBundle' => 'Doctrine\Bundle\DoctrineBundle\DoctrineBundle']);
    }
}
