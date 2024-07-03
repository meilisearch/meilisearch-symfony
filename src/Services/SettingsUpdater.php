<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Services;

use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\Event\SettingsUpdatedEvent;
use Meilisearch\Bundle\Exception\InvalidIndiceException;
use Meilisearch\Bundle\Exception\InvalidSettingName;
use Meilisearch\Bundle\Exception\TaskException;
use Meilisearch\Bundle\SearchService;
use Meilisearch\Bundle\SettingsProvider;
use Meilisearch\Client;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class SettingsUpdater
{
    private const DEFAULT_RESPONSE_TIMEOUT = 5000;

    private Client $searchClient;
    private EventDispatcherInterface $eventDispatcher;
    private Collection $configuration;

    public function __construct(SearchService $searchService, Client $searchClient, EventDispatcherInterface $eventDispatcher)
    {
        $this->searchClient = $searchClient;
        $this->eventDispatcher = $eventDispatcher;
        $this->configuration = $searchService->getConfiguration();
    }

    /**
     * @param non-empty-string  $indice
     * @param positive-int|null $responseTimeout
     */
    public function update(string $indice, ?int $responseTimeout = null): void
    {
        $index = (new Collection($this->configuration->get('indices')))->firstWhere('prefixed_name', $indice);

        if (!is_array($index)) {
            throw new InvalidIndiceException($indice);
        }

        if (!is_array($index['settings'] ?? null) || [] === $index['settings']) {
            return;
        }

        $indexName = $index['prefixed_name'];
        $indexInstance = $this->searchClient->index($indexName);
        $responseTimeout = $responseTimeout ?? self::DEFAULT_RESPONSE_TIMEOUT;

        foreach ($index['settings'] as $variable => $value) {
            $method = sprintf('update%s', ucfirst($variable));

            if (!method_exists($indexInstance, $method)) {
                throw new InvalidSettingName(sprintf('Invalid setting name: "%s"', $variable));
            }

            if (isset($value['_service']) && $value['_service'] instanceof SettingsProvider) {
                $value = $value['_service']();
            } elseif (('distinctAttribute' === $variable || 'proximityPrecision' === $variable || 'searchCutoffMs' === $variable) && is_array($value)) {
                $value = $value[0] ?? null;
            }

            // Update
            $task = $indexInstance->{$method}($value);

            // Get task information using uid
            $indexInstance->waitForTask($task['taskUid'], $responseTimeout);
            $task = $indexInstance->getTask($task['taskUid']);

            if ('failed' === $task['status']) {
                throw new TaskException($task['error']);
            }

            $this->eventDispatcher->dispatch(new SettingsUpdatedEvent($index['class'], $indexName, $variable));
        }
    }
}
