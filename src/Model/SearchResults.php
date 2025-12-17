<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Model;

/**
 * @template T of object
 */
final class SearchResults implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * @var array<T>
     */
    private readonly array $hits;
    private readonly int $hitsCount;
    private readonly string $query;
    private readonly int $processingTimeMs;

    private readonly ?int $limit;
    private readonly ?int $offset;
    private readonly ?int $estimatedTotalHits;
    private readonly ?int $nbHits;

    private readonly ?int $page;
    private readonly ?int $hitsPerPage;
    private readonly ?int $totalHits;
    private readonly ?int $totalPages;

    private readonly int $semanticHitCount;

    /**
     * @var array<string, mixed>
     */
    private readonly array $facetDistribution;

    /**
     * @var array<string, mixed>
     */
    private readonly array $facetStats;

    private readonly ?string $requestUid;

    private readonly array $raw;

    /**
     * @param array<T>             $hits
     * @param array<string, mixed> $facetDistribution
     * @param array<string, mixed> $facetStats
     */
    public function __construct(
        array $hits,
        string $query,
        int $processingTimeMs,

        ?int $limit = null,
        ?int $offset = null,
        ?int $estimatedTotalHits = null,
        ?int $nbHits = null,

        ?int $page = null,
        ?int $hitsPerPage = null,
        ?int $totalHits = null,
        ?int $totalPages = null,

        int $semanticHitCount = 0,

        array $facetDistribution = [],
        array $facetStats = [],

        ?string $requestUid = null,

        array $raw = [],
    ) {
        $this->hits = $hits;
        $this->hitsCount = \count($hits);
        $this->query = $query;
        $this->processingTimeMs = $processingTimeMs;

        $this->limit = $limit;
        $this->offset = $offset;
        $this->estimatedTotalHits = $estimatedTotalHits;
        $this->nbHits = $nbHits;

        $this->page = $page;
        $this->hitsPerPage = $hitsPerPage;
        $this->totalHits = $totalHits;
        $this->totalPages = $totalPages;

        $this->semanticHitCount = $semanticHitCount;

        $this->facetDistribution = $facetDistribution;
        $this->facetStats = $facetStats;

        $this->requestUid = $requestUid;

        $this->raw = $raw;
    }

    /**
     * @return array<T>
     */
    public function getHits(): array
    {
        return $this->hits;
    }

    public function getHitsCount(): int
    {
        return $this->hitsCount;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getProcessingTimeMs(): int
    {
        return $this->processingTimeMs;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function getEstimatedTotalHits(): ?int
    {
        return $this->estimatedTotalHits;
    }

    public function getNbHits(): ?int
    {
        return $this->nbHits;
    }

    public function getPage(): ?int
    {
        return $this->page;
    }

    public function getHitsPerPage(): ?int
    {
        return $this->hitsPerPage;
    }

    public function getTotalHits(): ?int
    {
        return $this->totalHits;
    }

    public function getTotalPages(): ?int
    {
        return $this->totalPages;
    }

    public function getSemanticHitCount(): int
    {
        return $this->semanticHitCount;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFacetDistribution(): array
    {
        return $this->facetDistribution;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFacetStats(): array
    {
        return $this->facetStats;
    }

    public function getRequestUid(): ?string
    {
        return $this->requestUid;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->hits[$offset]);
    }

    /**
     * @return T
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->hits[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Cannot modify hits');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Cannot modify hits');
    }

    public function count(): int
    {
        return \count($this->hits);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->hits);
    }

    public function jsonSerialize(): array
    {
        return [
            'hits' => $this->hits,
            'query' => $this->query,
            'processingTimeMs' => $this->processingTimeMs,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'estimatedTotalHits' => $this->estimatedTotalHits,
            'nbHits' => $this->nbHits,
            'page' => $this->page,
            'hitsPerPage' => $this->hitsPerPage,
            'totalHits' => $this->totalHits,
            'totalPages' => $this->totalPages,
            'semanticHitCount' => $this->semanticHitCount,
            'facetDistribution' => $this->facetDistribution,
            'facetStats' => $this->facetStats,
            'requestUid' => $this->requestUid,
        ];
    }
}
