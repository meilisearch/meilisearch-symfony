<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Services;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class UnixTimestampNormalizer implements NormalizerInterface
{
    /**
     * @param \DateTimeInterface $object
     */
    public function normalize(mixed $object, string $format = null, array $context = []): int
    {
        return $object->getTimestamp();
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof \DateTimeInterface && true === ($context['meilisearch'] ?? null);
    }
}
