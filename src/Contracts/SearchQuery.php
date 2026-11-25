<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Contracts;

/**
 * One query of a multi search, named by the class it searches rather than by an index.
 *
 * The arguments are those of a single search, so there is one vocabulary for both.
 */
final class SearchQuery
{
    /**
     * @param class-string         $className
     * @param array<string, mixed> $searchParams
     */
    public function __construct(
        public readonly string $className,
        public readonly string $query = '',
        public readonly array $searchParams = [],
    ) {
    }
}
