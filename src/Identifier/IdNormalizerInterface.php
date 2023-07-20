<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Identifier;

interface IdNormalizerInterface
{
    /**
     * Normalize object identifiers to Meilisearch-compatible primary keys.
     *
     * Single identifiers can be returned as-is (string|int), while composite
     * identifiers should be joined to a single string value.
     *
     * @param non-empty-array<non-empty-string, mixed> $identifiers
     */
    public function normalize(array $identifiers): string|int;

    /**
     * Denormalize flattened object identifier into a composite one.
     *
     * @param non-empty-string $identifier
     *
     * @return non-empty-array<non-empty-string, mixed>
     */
    public function denormalize(string $identifier): array;
}
