<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Exception;

final class NotSearchableException extends \InvalidArgumentException
{
    public function __construct(string $class, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Class "%s" is not searchable.', $class), $code, $previous);
    }
}
