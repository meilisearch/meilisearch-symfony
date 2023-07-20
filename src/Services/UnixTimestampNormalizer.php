<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Services;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class UnixTimestampNormalizer implements NormalizerInterface
{
    /**
     * @param \DateTimeInterface $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): int
    {
        return $data->getTimestamp();
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof \DateTimeInterface && true === ($context['meilisearch'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            \DateTimeInterface::class => true, // @codeCoverageIgnore
        ];
    }
}
