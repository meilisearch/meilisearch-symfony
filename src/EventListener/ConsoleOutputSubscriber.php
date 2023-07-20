<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\EventListener;

use Meilisearch\Bundle\Event\SettingsUpdatedEvent;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ConsoleOutputSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly OutputStyle $output)
    {
    }

    public function afterSettingsUpdate(SettingsUpdatedEvent $event): void
    {
        $this->output->writeln('<info>Setting "'.$event->getSetting().'" updated of "'.$event->getIndex().'".</info>');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SettingsUpdatedEvent::class => 'afterSettingsUpdate',
        ];
    }
}
