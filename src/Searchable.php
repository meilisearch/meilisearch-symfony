<?php

declare(strict_types=1);

namespace Meilisearch\Bundle;

/**
 * @deprecated Since 0.16, use `Meilisearch\Bundle\SearchableObject` instead.
 */
final class Searchable
{
    /**
     * @deprecated use `Meilisearch\Bundle\SearchableObject::NORMALIZATION_FORMAT` instead
     */
    public const NORMALIZATION_FORMAT = 'searchableArray';

    /**
     * @deprecated use `Meilisearch\Bundle\SearchableObject::NORMALIZATION_GROUP` instead
     */
    public const NORMALIZATION_GROUP = 'searchable';
}
