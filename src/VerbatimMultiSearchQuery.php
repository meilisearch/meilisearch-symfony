<?php

declare(strict_types=1);

namespace Meilisearch\Bundle;

use Meilisearch\Contracts\MultiSearchQuery;

/**
 * The multi search counterpart of {@see VerbatimSearchQuery}: it keeps caller-supplied search
 * parameters away from the typed hydration of {@see MultiSearchQuery::fromArray()}, which drops
 * every key it does not know and throws on null filters and on values of an unexpected type.
 *
 * @internal
 */
final class VerbatimMultiSearchQuery extends MultiSearchQuery
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
