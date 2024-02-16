<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\EventListener;

use Meilisearch\Bundle\Event\SettingsUpdatedEvent;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ConsoleOutputSubscriber implements EventSubscriberInterface
{
    private OutputStyle $io;

    public function __construct(OutputStyle $io)
    {
        $this->io = $io;
    }

    public function afterSettingsUpdate(SettingsUpdatedEvent $event): void
    {
        $this->io->writeln('<info>Settings updated of "'.$event->getIndex().'".</info>');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SettingsUpdatedEvent::class => 'afterSettingsUpdate',
        ];
    }
}
