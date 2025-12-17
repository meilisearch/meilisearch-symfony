<?php

declare(strict_types=1);

namespace Meilisearch\Bundle;

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class SearchableObject
{
    public const NORMALIZATION_FORMAT = 'searchableArray';
    public const NORMALIZATION_GROUP = 'searchable';

    /**
     * @var array<mixed>
     */
    private array $normalizationContext;

    /**
     * @param non-empty-string $indexUid
     * @param non-empty-string $primaryKey
     * @param array<mixed>     $normalizationContext
     */
    public function __construct(
        private readonly string $indexUid,
        private readonly string $primaryKey,
        private readonly object $object,
        private readonly \Stringable|string|int $identifier,
        private readonly NormalizerInterface $normalizer,
        array $normalizationContext = [],
    ) {
        $this->normalizationContext = array_merge($normalizationContext, ['meilisearch' => true]);
    }

    /**
     * @return non-empty-string
     */
    public function getIndexUid(): string
    {
        return $this->indexUid;
    }

    /**
     * @return non-empty-string
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function getIdentifier(): \Stringable|string|int
    {
        return $this->identifier;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ExceptionInterface
     */
    public function getSearchableArray(): array
    {
        $context = $this->normalizationContext;

        if (Kernel::VERSION_ID >= 70100) {
            $context[DateTimeNormalizer::FORMAT_KEY] = 'U';
            $context[DateTimeNormalizer::CAST_KEY] = 'int';
        }

        if ($this->object instanceof NormalizableInterface) {
            return $this->object->normalize($this->normalizer, self::NORMALIZATION_FORMAT, $context);
        }

        return $this->normalizer->normalize($this->object, self::NORMALIZATION_FORMAT, $context);
    }
}
