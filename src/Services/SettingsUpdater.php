<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Services;

use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\Event\SettingsUpdatedEvent;
use Meilisearch\Bundle\Exception\InvalidIndiceException;
use Meilisearch\Bundle\Exception\InvalidSettingName;
use Meilisearch\Bundle\Exception\TaskException;
use Meilisearch\Bundle\SearchManagerInterface;
use Meilisearch\Bundle\SettingsProvider;
use Meilisearch\Client;
use Meilisearch\Contracts\Task;
use Meilisearch\Contracts\TaskStatus;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function Meilisearch\partial;

final class SettingsUpdater
{
    private const DEFAULT_RESPONSE_TIMEOUT = 5000;

    private Collection $configuration;

    public function __construct(
        SearchManagerInterface $searchManager,
        private readonly Client $searchClient,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        $this->configuration = $searchManager->getConfiguration();
    }

    /**
     * @param non-empty-string  $indice
     * @param positive-int|null $responseTimeout
     */
    public function update(string $indice, ?int $responseTimeout = null, ?string $prefixedName = null): void
    {
        $index = (new Collection($this->configuration->get('indices')))->firstWhere('prefixed_name', $indice);

        if (!\is_array($index)) {
            throw new InvalidIndiceException($indice);
        }

        if (!\is_array($index['settings'] ?? null) || [] === $index['settings']) {
            return;
        }

        $indexName = $prefixedName ?? $index['prefixed_name'];
        $indexInstance = $this->searchClient->index($indexName);
        $responseTimeout = $responseTimeout ?? self::DEFAULT_RESPONSE_TIMEOUT;

        foreach ($index['settings'] as $variable => $value) {
            $method = \sprintf('update%s', ucfirst($variable));

            if (!method_exists($indexInstance, $method)) {
                throw new InvalidSettingName(\sprintf('Invalid setting name: "%s"', $variable));
            }

            if (isset($value['_service']) && $value['_service'] instanceof SettingsProvider) {
                $value = $value['_service']();
            } elseif (('distinctAttribute' === $variable || 'proximityPrecision' === $variable || 'searchCutoffMs' === $variable) && \is_array($value)) {
                $value = $value[0] ?? null;
            }

            // Update
            $task = $indexInstance->{$method}($value);
            if (\is_array($task)) {
                $http = (new \ReflectionObject($this->searchClient))->getProperty('http')->getValue($this->searchClient);
                $task = Task::fromArray($task, partial(Engine::waitTask(...), $http));
            }
            $task = $task->wait($responseTimeout);

            if (TaskStatus::Failed === $task->getStatus()) {
                throw new TaskException($task->getError()->message);
            }

            $this->eventDispatcher->dispatch(new SettingsUpdatedEvent($index['class'], $indexName, $variable));
        }
    }
}
