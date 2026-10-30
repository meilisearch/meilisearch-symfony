<?php

declare(strict_types=1);

namespace Meilisearch\Bundle;

use Meilisearch\Contracts\SearchQuery;

/**
 * Keeps caller-supplied search parameters away from the typed hydration of
 * {@see SearchQuery::fromArray()}, which drops every key it does not know and
 * throws on null filters and on values of an unexpected type.
 *
 * @internal
 */
final class VerbatimSearchQuery extends SearchQuery
{
    /**
     * @param array<mixed> $searchParams
     */
    public function __construct(private readonly array $searchParams)
    {
    }

    public function toArray(): array
    {
        return $this->searchParams + parent::toArray();
    }
}
