<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Identifier;

final class DefaultIdNormalizer implements IdNormalizerInterface
{
    public function normalize(array $identifiers): string|int
    {
        if (1 === \count($identifiers)) {
            $identifier = reset($identifiers);

            if (\is_object($identifier) && method_exists($identifier, '__toString')) {
                return (string) $identifier;
            }

            if (\is_object($identifier)) {
                throw new \InvalidArgumentException('Identifier object must implement __toString().');
            }

            return $identifier;
        }

        ksort($identifiers);

        $json = json_encode($identifiers, \JSON_THROW_ON_ERROR);
        $base64 = base64_encode($json);

        return rtrim(strtr($base64, '+/', '-_'), '=');
    }

    public function denormalize(string $identifier): array
    {
        $padded = $identifier.str_repeat('=', (4 - \strlen($identifier) % 4) % 4);
        $base64 = strtr($padded, '-_', '+/');

        return json_decode(base64_decode($base64), true, 512, \JSON_THROW_ON_ERROR);
    }
}
