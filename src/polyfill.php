<?php

declare(strict_types=1);

namespace Meilisearch\Contracts {
    use Meilisearch\Contracts\TaskDetails\DocumentAdditionOrUpdateDetails;
    use Meilisearch\Contracts\TaskDetails\DocumentDeletionDetails;
    use Meilisearch\Contracts\TaskDetails\DocumentEditionDetails;
    use Meilisearch\Contracts\TaskDetails\DumpCreationDetails;
    use Meilisearch\Contracts\TaskDetails\IndexCreationDetails;
    use Meilisearch\Contracts\TaskDetails\IndexDeletionDetails;
    use Meilisearch\Contracts\TaskDetails\IndexSwapDetails;
    use Meilisearch\Contracts\TaskDetails\IndexUpdateDetails;
    use Meilisearch\Contracts\TaskDetails\SettingsUpdateDetails;
    use Meilisearch\Contracts\TaskDetails\TaskCancelationDetails;
    use Meilisearch\Contracts\TaskDetails\TaskDeletionDetails;
    use Meilisearch\Contracts\TaskDetails\UnknownTaskDetails;
    use Meilisearch\Exceptions\LogicException;

    if (!class_exists(TaskStatus::class, false)) {
        enum TaskStatus: string
        {
            case Canceled = 'canceled';
            case Enqueued = 'enqueued';
            case Failed = 'failed';
            case Succeeded = 'succeeded';
            case Processing = 'processing';
            case Unknown = 'unknown';
        }
    }

    if (!class_exists(TaskType::class, false)) {
        enum TaskType: string
        {
            case IndexCreation = 'indexCreation';
            case IndexUpdate = 'indexUpdate';
            case IndexDeletion = 'indexDeletion';
            case IndexSwap = 'indexSwap';
            case DocumentAdditionOrUpdate = 'documentAdditionOrUpdate';
            case DocumentDeletion = 'documentDeletion';
            case DocumentEdition = 'documentEdition';
            case SettingsUpdate = 'settingsUpdate';
            case DumpCreation = 'dumpCreation';
            case TaskCancelation = 'taskCancelation';
            case TaskDeletion = 'taskDeletion';
            case SnapshotCreation = 'snapshotCreation';
            case Unknown = 'unknown';
        }
    }

    if (!interface_exists(TaskDetails::class)) {
        /**
         * @template T of array
         */
        interface TaskDetails
        {
            /**
             * @param T $data
             */
            public static function fromArray(array $data): self;
        }
    }

    if (!class_exists(TaskError::class, false)) {
        final class TaskError
        {
            /**
             * @param non-empty-string $message
             * @param non-empty-string $code
             * @param non-empty-string $type
             * @param non-empty-string $link
             */
            public function __construct(
                public readonly string $message,
                public readonly string $code,
                public readonly string $type,
                public readonly string $link,
            ) {
            }

            /**
             * @param array{
             *     message: non-empty-string,
             *     code: non-empty-string,
             *     type: non-empty-string,
             *     link: non-empty-string
             * } $data
             */
            public static function fromArray(array $data): self
            {
                return new self(
                    $data['message'],
                    $data['code'],
                    $data['type'],
                    $data['link'],
                );
            }
        }
    }

    if (!class_exists(Task::class, false)) {
        final class Task implements \ArrayAccess
        {
            /**
             * @param non-negative-int                   $taskUid
             * @param non-empty-string|null              $indexUid
             * @param non-empty-string|null              $duration
             * @param array<mixed>                       $raw
             * @param \Closure(int, int, int): Task|null $await
             */
            public function __construct(
                private readonly int $taskUid,
                private readonly ?string $indexUid,
                private readonly TaskStatus $status,
                private readonly TaskType $type,
                private readonly \DateTimeImmutable $enqueuedAt,
                private readonly ?\DateTimeImmutable $startedAt = null,
                private readonly ?\DateTimeImmutable $finishedAt = null,
                private readonly ?string $duration = null,
                private readonly ?int $canceledBy = null,
                private readonly ?int $batchUid = null,
                private readonly ?TaskDetails $details = null,
                private readonly ?TaskError $error = null,
                private readonly array $raw = [],
                private readonly ?\Closure $await = null,
            ) {
            }

            /**
             * @return non-negative-int
             */
            public function getTaskUid(): int
            {
                return $this->taskUid;
            }

            /**
             * @return non-empty-string|null
             */
            public function getIndexUid(): ?string
            {
                return $this->indexUid;
            }

            public function getStatus(): TaskStatus
            {
                return $this->status;
            }

            public function getType(): TaskType
            {
                return $this->type;
            }

            public function getEnqueuedAt(): \DateTimeImmutable
            {
                return $this->enqueuedAt;
            }

            public function getStartedAt(): ?\DateTimeImmutable
            {
                return $this->startedAt;
            }

            public function getFinishedAt(): ?\DateTimeImmutable
            {
                return $this->finishedAt;
            }

            /**
             * @return non-empty-string|null
             */
            public function getDuration(): ?string
            {
                return $this->duration;
            }

            public function getCanceledBy(): ?int
            {
                return $this->canceledBy;
            }

            public function getBatchUid(): ?int
            {
                return $this->batchUid;
            }

            public function getDetails(): ?TaskDetails
            {
                return $this->details;
            }

            public function getError(): ?TaskError
            {
                return $this->error;
            }

            public function isFinished(): bool
            {
                return TaskStatus::Enqueued !== $this->status && TaskStatus::Processing !== $this->status;
            }

            public function wait(int $timeoutInMs = 5000, int $intervalInMs = 50): Task
            {
                if ($this->isFinished()) {
                    return $this;
                }

                if (null !== $this->await) {
                    return ($this->await)($this->taskUid, $timeoutInMs, $intervalInMs);
                }

                throw new LogicException(\sprintf('Cannot wait for task because wait function is not provided.'));
            }

            /**
             * @return array<mixed>
             */
            public function toArray(): array
            {
                return $this->raw;
            }

            public function offsetSet(mixed $offset, mixed $value): void
            {
                throw new LogicException('The Task object is immutable.');
            }

            public function offsetExists(mixed $offset): bool
            {
                return \array_key_exists($offset, $this->raw);
            }

            public function offsetUnset(mixed $offset): void
            {
                throw new LogicException('The Task object is immutable.');
            }

            public function offsetGet(mixed $offset): mixed
            {
                return $this->raw[$offset] ?? null;
            }

            /**
             * @param array{
             *     taskUid?: int,
             *     uid?: int,
             *     indexUid?: non-empty-string,
             *     status: non-empty-string,
             *     type: non-empty-string,
             *     enqueuedAt: non-empty-string,
             *     startedAt?: non-empty-string|null,
             *     finishedAt?: non-empty-string|null,
             *     duration?: non-empty-string|null,
             *     canceledBy?: int,
             *     batchUid?: int,
             *     details?: array<mixed>|null,
             *     error?: array<mixed>|null
             * } $data
             * @param \Closure(int, int, int): Task|null $await
             */
            public static function fromArray(array $data, ?\Closure $await = null): Task
            {
                $details = $data['details'] ?? null;
                $type = TaskType::tryFrom($data['type']) ?? TaskType::Unknown;

                return new self(
                    $data['taskUid'] ?? $data['uid'],
                    $data['indexUid'] ?? null,
                    TaskStatus::tryFrom($data['status']) ?? TaskStatus::Unknown,
                    $type,
                    new \DateTimeImmutable($data['enqueuedAt']),
                    \array_key_exists('startedAt', $data) && null !== $data['startedAt'] ? new \DateTimeImmutable($data['startedAt']) : null,
                    \array_key_exists('finishedAt', $data) && null !== $data['finishedAt'] ? new \DateTimeImmutable($data['finishedAt']) : null,
                    $data['duration'] ?? null,
                    $data['canceledBy'] ?? null,
                    $data['batchUid'] ?? null,
                    match ($type) {
                        TaskType::IndexCreation => null !== $details ? IndexCreationDetails::fromArray($details) : null,
                        TaskType::IndexUpdate => null !== $details ? IndexUpdateDetails::fromArray($details) : null,
                        TaskType::IndexDeletion => null !== $details ? IndexDeletionDetails::fromArray($details) : null,
                        TaskType::IndexSwap => null !== $details ? IndexSwapDetails::fromArray($details) : null,
                        TaskType::DocumentAdditionOrUpdate => null !== $details ? DocumentAdditionOrUpdateDetails::fromArray($details) : null,
                        TaskType::DocumentDeletion => null !== $details ? DocumentDeletionDetails::fromArray($details) : null,
                        TaskType::DocumentEdition => null !== $details ? DocumentEditionDetails::fromArray($details) : null,
                        TaskType::SettingsUpdate => null !== $details ? SettingsUpdateDetails::fromArray($details) : null,
                        TaskType::DumpCreation => null !== $details ? DumpCreationDetails::fromArray($details) : null,
                        TaskType::TaskCancelation => null !== $details ? TaskCancelationDetails::fromArray($details) : null,
                        TaskType::TaskDeletion => null !== $details ? TaskDeletionDetails::fromArray($details) : null,
                        // It’s intentional that SnapshotCreation tasks don’t have a details object
                        // (no SnapshotCreationDetails exists and tests don’t exercise any details)
                        TaskType::SnapshotCreation => null,
                        TaskType::Unknown => UnknownTaskDetails::fromArray($details ?? []),
                    },
                    \array_key_exists('error', $data) && null !== $data['error'] ? TaskError::fromArray($data['error']) : null,
                    $data,
                    $await,
                );
            }
        }
    }
}

namespace Meilisearch\Contracts\TaskDetails {
    use Meilisearch\Contracts\TaskDetails;

    if (!class_exists(DocumentAdditionOrUpdateDetails::class, false)) {
        /**
         * @implements TaskDetails<array{
         *     receivedDocuments: non-negative-int,
         *     indexedDocuments: non-negative-int|null
         * }>
         */
        final class DocumentAdditionOrUpdateDetails implements TaskDetails
        {
            /**
             * @param non-negative-int      $receivedDocuments number of documents received
             * @param non-negative-int|null $indexedDocuments  Number of documents indexed. `null` while the task status is enqueued or processing.
             */
            public function __construct(
                public readonly int $receivedDocuments,
                public readonly ?int $indexedDocuments,
            ) {
            }

            public static function fromArray(array $data): self
            {
                return new self(
                    $data['receivedDocuments'],
                    $data['indexedDocuments'] ?? null,
                );
            }
        }
    }

    if (!class_exists(DocumentDeletionDetails::class, false)) {
        /**
         * @implements TaskDetails<array{
         *     providedIds?: non-negative-int,
         *     originalFilter?: string|null,
         *     deletedDocuments?: non-negative-int|null
         * }>
         */
        final class DocumentDeletionDetails implements TaskDetails
        {
            /**
             * @param non-negative-int|null $providedIds      number of documents queued for deletion
             * @param string|null           $originalFilter   The filter used to delete documents. Null if it was not specified.
             * @param int|null              $deletedDocuments Number of documents deleted. `null` while the task status is enqueued or processing.
             */
            public function __construct(
                public readonly ?int $providedIds,
                public readonly ?string $originalFilter,
                public readonly ?int $deletedDocuments,
            ) {
            }

            public static function fromArray(array $data): self
            {
                return new self(
                    $data['providedIds'] ?? null,
                    $data['originalFilter'] ?? null,
                    $data['deletedDocuments'] ?? null,
                );
            }
        }
    }

    if (!class_exists(DocumentEditionDetails::class, false)) {
        /**
         * @implements TaskDetails<array{
         *     context: array<non-empty-string, scalar|null>,
         *     deletedDocuments: non-negative-int|null,
         *     editedDocuments: non-negative-int|null,
         *     function: string|null,
         *     originalFilter: string|null
         * }>
         */
        final class DocumentEditionDetails implements TaskDetails
        {
            /**
             * @param array<non-empty-string, scalar|null> $context
             */
            public function __construct(
                public readonly array $context,
                public readonly ?int $deletedDocuments,
                public readonly ?int $editedDocuments,
                public readonly ?string $function,
                public readonly ?string $originalFilter,
            ) {
            }

            public static function fromArray(array $data): self
            {
                return new self(
                    $data['context'],
                    $data['deletedDocuments'],
                    $data['editedDocuments'],
                    $data['function'],
                    $data['originalFilter'],
                );
            }
        }
    }

    if (!class_exists(DumpCreationDetails::class, false)) {
        /**
         * @implements TaskDetails<array{
         *     dumpUid: non-empty-string|null
         * }>
         */
        final class DumpCreationDetails implements TaskDetails
        {
            /**
             * @param non-empty-string|null $dumpUid
             */
            public function __construct(
                public readonly ?string $dumpUid,
            ) {
            }

            public static function fromArray(array $data): self
            {
                return new self(
                    $data['dumpUid'],
                );
            }
        }
    }

    if (!class_exists(IndexCreationDetails::class, false)) {
        /**
         * @implements TaskDetails<array{
         *     primaryKey: non-empty-string|null
         * }>
         */
        final class IndexCreationDetails implements TaskDetails
        {
            /**
             * @param non-empty-string|null $primaryKey Value of the primaryKey field supplied during index creation. `null` if it was not specified.
             */
            public function __construct(
                public readonly ?string $primaryKey,
            ) {
            }

            public static function fromArray(array $data): self
            {
                return new self(
                    $data['primaryKey'],
                );
            }
        }
    }

    if (!class_exists(IndexDeletionDetails::class, false)) {
        /**
         * @implements TaskDetails<array{
         *     deletedDocuments: non-negative-int|null
         * }>
         */
        final class IndexDeletionDetails implements TaskDetails
        {
            /**
             * @param non-negative-int|null $deletedDocuments Number of deleted documents. This should equal the total number of documents in the deleted index. `null` while the task status is enqueued or processing.
             */
            public function __construct(
                public readonly ?int $deletedDocuments,
            ) {
            }

            public static function fromArray(array $data): self
            {
                return new self(
                    $data['deletedDocuments'],
                );
            }
        }
    }

    if (!class_exists(IndexSwapDetails::class, false)) {
        /**
         * @implements TaskDetails<array{
         *     swaps: array<array{indexes: mixed, rename: bool}>
         * }>
         */
        final class IndexSwapDetails implements TaskDetails
        {
            /**
             * @param array<array{indexes: mixed, rename: bool}> $swaps
             */
            public function __construct(
                public readonly array $swaps,
            ) {
            }

            public static function fromArray(array $data): self
            {
                return new self(
                    $data['swaps'],
                );
            }
        }
    }

    if (!class_exists(IndexUpdateDetails::class, false)) {
        /**
         * @implements TaskDetails<array{
         *     primaryKey: non-empty-string|null
         * }>
         */
        final class IndexUpdateDetails implements TaskDetails
        {
            /**
             * @param non-empty-string|null $primaryKey Value of the primaryKey field supplied during index creation. `null` if it was not specified.
             */
            public function __construct(
                public readonly ?string $primaryKey,
            ) {
            }

            public static function fromArray(array $data): self
            {
                return new self(
                    $data['primaryKey'],
                );
            }
        }
    }

    if (!class_exists(SettingsUpdateDetails::class, false)) {
        /**
         * @implements TaskDetails<array{
         *     dictionary?: list<string>,
         *     displayedAttributes?: list<string>,
         *     distinctAttribute?: string,
         *     embedders?: non-empty-array<non-empty-string, array{
         *         apiKey?: string,
         *         binaryQuantized?: bool,
         *         dimensions?: int,
         *         distribution?: array{mean: float, sigma: float},
         *         documentTemplate?: string,
         *         documentTemplateMaxBytes?: int,
         *         indexingEmbedder?: array{model: string, source: string},
         *         model?: string,
         *         pooling?: string,
         *         request?: array<string, mixed>,
         *         response?: array<string, mixed>,
         *         revision?: string,
         *         searchEmbedder?: array{model: string, source: string},
         *         source?: string,
         *         url?: string
         *     }>,
         *     faceting?: array{maxValuesPerFacet: non-negative-int, sortFacetValuesBy: array<string, 'alpha'|'count'>}|null,
         *     facetSearch?: bool,
         *     filterableAttributes?: list<string|array{attributePatterns: list<string>, features: array{facetSearch: bool, filter: array{equality: bool, comparison: bool}}}>|null,
         *     localizedAttributes?: list<array{locales: list<non-empty-string>, attributePatterns: list<string>}>,
         *     nonSeparatorTokens?: list<string>,
         *     pagination?: array{maxTotalHits: non-negative-int},
         *     prefixSearch?: non-empty-string|null,
         *     proximityPrecision?: 'byWord'|'byAttribute',
         *     rankingRules?: list<non-empty-string>,
         *     searchableAttributes?: list<non-empty-string>,
         *     searchCutoffMs?: non-negative-int,
         *     separatorTokens?: list<string>,
         *     sortableAttributes?: list<non-empty-string>,
         *     stopWords?: list<string>,
         *     synonyms?: array<string, list<string>>,
         *     typoTolerance?: array{
         *         enabled: bool,
         *         minWordSizeForTypos: array{oneTypo: int, twoTypos: int},
         *         disableOnWords: list<string>,
         *         disableOnAttributes: list<string>,
         *         disableOnNumbers: bool
         *     }
         * }>
         */
        final class SettingsUpdateDetails implements TaskDetails
        {
            /**
             * @param list<string>|null $dictionary
             * @param list<string>|null $displayedAttributes
             * @param non-empty-array<non-empty-string, array{
             *     apiKey?: string,
             *     binaryQuantized?: bool,
             *     dimensions?: int,
             *     distribution?: array{mean: float, sigma: float},
             *     documentTemplate?: string,
             *     documentTemplateMaxBytes?: int,
             *     indexingEmbedder?: array{model: string, source: string},
             *     model?: string,
             *     pooling?: string,
             *     request?: array<string, mixed>,
             *     response?: array<string, mixed>,
             *     revision?: string,
             *     searchEmbedder?: array{model: string, source: string},
             *     source?: string,
             *     url?: string
             * }>|null $embedders
             * @param array{maxValuesPerFacet: non-negative-int, sortFacetValuesBy: array<string, 'alpha'|'count'>}|null                                            $faceting
             * @param list<string|array{attributePatterns: list<string>, features: array{facetSearch: bool, filter: array{equality: bool, comparison: bool}}}>|null $filterableAttributes
             * @param list<array{locales: list<non-empty-string>, attributePatterns: list<string>}>|null                                                            $localizedAttributes
             * @param list<string>|null                                                                                                                             $nonSeparatorTokens
             * @param array{maxTotalHits: non-negative-int}|null                                                                                                    $pagination
             * @param 'indexingTime'|'disabled'|null                                                                                                                $prefixSearch
             * @param 'byWord'|'byAttribute'|null                                                                                                                   $proximityPrecision
             * @param list<non-empty-string>|null                                                                                                                   $rankingRules
             * @param list<non-empty-string>|null                                                                                                                   $searchableAttributes
             * @param non-negative-int|null                                                                                                                         $searchCutoffMs
             * @param list<string>                                                                                                                                  $separatorTokens
             * @param list<non-empty-string>|null                                                                                                                   $sortableAttributes
             * @param list<string>|null                                                                                                                             $stopWords
             * @param array<string, list<string>>|null                                                                                                              $synonyms
             * @param array{
             *     enabled: bool,
             *     minWordSizeForTypos: array{oneTypo: int, twoTypos: int},
             *     disableOnWords: list<string>,
             *     disableOnAttributes: list<string>,
             *     disableOnNumbers: bool
             * }|null $typoTolerance
             */
            public function __construct(
                public readonly ?array $dictionary,
                public readonly ?array $displayedAttributes,
                public readonly ?string $distinctAttribute,
                public readonly ?array $embedders,
                public readonly ?array $faceting,
                public readonly ?bool $facetSearch,
                public readonly ?array $filterableAttributes,
                public readonly ?array $localizedAttributes,
                public readonly ?array $nonSeparatorTokens,
                public readonly ?array $pagination,
                public readonly ?string $prefixSearch,
                public readonly ?string $proximityPrecision,
                public readonly ?array $rankingRules,
                public readonly ?array $searchableAttributes,
                public readonly ?int $searchCutoffMs,
                public readonly ?array $separatorTokens,
                public readonly ?array $sortableAttributes,
                public readonly ?array $stopWords,
                public readonly ?array $synonyms,
                public readonly ?array $typoTolerance,
            ) {
            }

            public static function fromArray(array $data): self
            {
                return new self(
                    $data['dictionary'] ?? null,
                    $data['displayedAttributes'] ?? null,
                    $data['distinctAttribute'] ?? null,
                    $data['embedders'] ?? null,
                    $data['faceting'] ?? null,
                    $data['facetSearch'] ?? null,
                    $data['filterableAttributes'] ?? null,
                    $data['localizedAttributes'] ?? null,
                    $data['nonSeparatorTokens'] ?? null,
                    $data['pagination'] ?? null,
                    $data['prefixSearch'] ?? null,
                    $data['proximityPrecision'] ?? null,
                    $data['rankingRules'] ?? null,
                    $data['searchableAttributes'] ?? null,
                    $data['searchCutoffMs'] ?? null,
                    $data['separatorTokens'] ?? null,
                    $data['sortableAttributes'] ?? null,
                    $data['stopWords'] ?? null,
                    $data['synonyms'] ?? null,
                    $data['typoTolerance'] ?? null,
                );
            }
        }
    }

    if (!class_exists(TaskCancelationDetails::class, false)) {
        /**
         * @implements TaskDetails<array{
         *     matchedTasks: non-negative-int|null,
         *     canceledTasks: non-negative-int|null,
         *     originalFilter: string|null
         * }>
         */
        final class TaskCancelationDetails implements TaskDetails
        {
            /**
             * @param non-negative-int|null $matchedTasks   The number of matched tasks. If the API key used for the request doesn’t have access to an index, tasks relating to that index will not be included in matchedTasks.
             * @param non-negative-int|null $canceledTasks  The number of tasks successfully canceled. If the task cancellation fails, this will be 0. null when the task status is enqueued or processing.
             * @param string|null           $originalFilter the filter used in the cancel task request
             */
            public function __construct(
                public readonly ?int $matchedTasks,
                public readonly ?int $canceledTasks,
                public readonly ?string $originalFilter,
            ) {
            }

            public static function fromArray(array $data): self
            {
                return new self(
                    $data['matchedTasks'],
                    $data['canceledTasks'],
                    $data['originalFilter'],
                );
            }
        }
    }

    if (!class_exists(TaskDeletionDetails::class, false)) {
        /**
         * @implements TaskDetails<array{
         *     matchedTasks: non-negative-int|null,
         *     deletedTasks: non-negative-int|null,
         *     originalFilter: string|null
         * }>
         */
        final class TaskDeletionDetails implements TaskDetails
        {
            /**
             * @param non-negative-int|null $matchedTasks   The number of matched tasks. If the API key used for the request doesn’t have access to an index, tasks relating to that index will not be included in matchedTasks.
             * @param non-negative-int|null $deletedTasks   The number of tasks successfully deleted. If the task deletion fails, this will be 0. null when the task status is enqueued or processing.
             * @param string|null           $originalFilter the filter used in the delete task request
             */
            public function __construct(
                public readonly ?int $matchedTasks,
                public readonly ?int $deletedTasks,
                public readonly ?string $originalFilter,
            ) {
            }

            public static function fromArray(array $data): self
            {
                return new self(
                    $data['matchedTasks'],
                    $data['deletedTasks'],
                    $data['originalFilter'],
                );
            }
        }
    }

    if (!class_exists(UnknownTaskDetails::class, false)) {
        /**
         * @implements TaskDetails<array<mixed>>
         */
        final class UnknownTaskDetails implements TaskDetails
        {
            /**
             * @param array<mixed> $data
             */
            public function __construct(
                public readonly array $data,
            ) {
            }

            public static function fromArray(array $data): self
            {
                return new self($data);
            }
        }
    }
}

namespace Meilisearch\Exceptions {
    if (!class_exists(LogicException::class, false)) {
        final class LogicException extends \LogicException implements ExceptionInterface
        {
        }
    }
}

namespace Meilisearch {
    if (!\function_exists(__NAMESPACE__.'\partial')) {
        /**
         * @internal
         *
         * Creates a partially applied function by binding initial arguments to the given callable.
         *
         * Returns a Closure that, when invoked, calls the original callable with the bound arguments prepended
         * to any new ones.
         *
         * Used internally to build reusable “waiter” functions (e.g., binding the HTTP client to
         * task-waiting logic) and reduce repetitive argument passing.
         */
        function partial(callable $func, ...$boundArgs): \Closure
        {
            return static fn (...$remainingArgs) => $func(...array_merge($boundArgs, $remainingArgs));
        }
    }
}
