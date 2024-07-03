<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class SettingsUpdatedEvent extends Event
{
    /**
     * @var class-string
     */
    private string $class;

    /**
     * @var non-empty-string
     */
    private string $index;

    /**
     * @var non-empty-string
     */
    private string $setting;

    /**
     * @param class-string     $class
     * @param non-empty-string $index
     * @param non-empty-string $setting
     */
    public function __construct(string $class, string $index, string $setting)
    {
        $this->index = $index;
        $this->class = $class;
        $this->setting = $setting;
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
