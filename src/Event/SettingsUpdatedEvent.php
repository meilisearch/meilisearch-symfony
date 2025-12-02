<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class SettingsUpdatedEvent extends Event
{
    /**
     * @param class-string     $class
     * @param non-empty-string $index
     * @param non-empty-string $setting
     */
    public function __construct(
        private readonly string $class,
        private readonly string $index,
        private readonly string $setting,
    ) {
    }

    /**
     * @return class-string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * @return non-empty-string
     */
    public function getIndex(): string
    {
        return $this->index;
    }

    /**
     * @return non-empty-string
     */
    public function getSetting(): string
    {
        return $this->setting;
    }
}
