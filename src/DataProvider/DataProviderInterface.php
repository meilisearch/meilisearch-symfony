<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\DataProvider;

/**
 * @template T of object
 */
interface DataProviderInterface
{
    /**
     * @param positive-int     $limit
     * @param non-negative-int $offset
     *
     * @return array<T>
     */
    public function provide(int $limit, int $offset): array;

    /**
     * @param array<mixed> $identifiers
     *
     * @return iterable<T>
     */
    public function loadByIdentifiers(array $identifiers): iterable;

    /**
     * Returns the identifier of this object as an array with a field name as a key.
     *
     * @return non-empty-array<non-empty-string, mixed>
     */
    public function getIdentifierValues(object $object): array;

    /**
     * @param non-empty-array<non-empty-string, mixed> $identifiers
     *
     * @return non-empty-string|int
     */
    public function normalizeIdentifiers(array $identifiers): string|int;

    /**
     * @param non-empty-string $identifier
     *
     * @return non-empty-array<string, mixed>
     */
    public function denormalizeIdentifier(string $identifier): array;

    public function cleanup(): void;
}
