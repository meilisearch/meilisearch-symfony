<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Exception;

final class InvalidIndiceException extends \InvalidArgumentException
{
    public function __construct(string $indice, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Meilisearch index for "%s" was not found.', $indice), $code, $previous);
    }
}
