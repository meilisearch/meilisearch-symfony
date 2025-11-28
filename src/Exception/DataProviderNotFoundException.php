<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Exception;

final class DataProviderNotFoundException extends \InvalidArgumentException
{
    public function __construct(string $indexName, string $className, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Data provider for index "%s" and class "%s" was not found.', $indexName, $className), $code, $previous);
    }
}
