<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Contracts;

use Meilisearch\Contracts\SearchQuery as EngineQuery;

class SearchQuery
{
    /**
     * @var class-string
     */
    private string $className;
    private ?string $q = null;
    private ?array $filter = null;
    private ?array $attributesToRetrieve = null;
    private ?array $attributesToCrop = null;
    private ?int $cropLength;
    private ?array $attributesToHighlight = null;
    private ?string $cropMarker = null;
    private ?string $highlightPreTag = null;
    private ?string $highlightPostTag = null;
    private ?array $facets = null;
    private ?bool $showMatchesPosition = null;
    private ?array $sort = null;
    private ?string $matchingStrategy = null;
    private ?int $offset = null;
    private ?int $limit = null;
    private ?int $hitsPerPage = null;
    private ?int $page = null;
    private ?array $vector = null;
    private ?array $attributesToSearchOn = null;
    private ?bool $showRankingScore = null;
    private ?bool $showRankingScoreDetails = null;

    /**
     * @param class-string $className
     */
    public function __construct(string $className)
    {
        $this->className = $className;
    }

    /**
     * @return class-string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    public function setQuery(string $q): SearchQuery
    {
        $this->q = $q;

        return $this;
    }

    public function getQuery(): ?string
    {
        return $this->q;
    }

    public function setFilter(array $filter): SearchQuery
    {
        $this->filter = $filter;

        return $this;
    }

    public function getFilter(): ?array
    {
        return $this->filter;
    }

    public function setAttributesToRetrieve(array $attributesToRetrieve): SearchQuery
    {
        $this->attributesToRetrieve = $attributesToRetrieve;

        return $this;
    }

    public function setAttributesToCrop(array $attributesToCrop): SearchQuery
    {
        $this->attributesToCrop = $attributesToCrop;

        return $this;
    }

    public function setCropLength(?int $cropLength): SearchQuery
    {
        $this->cropLength = $cropLength;

        return $this;
    }

    public function setAttributesToHighlight(array $attributesToHighlight): SearchQuery
    {
        $this->attributesToHighlight = $attributesToHighlight;

        return $this;
    }

    public function setCropMarker(string $cropMarker): SearchQuery
    {
        $this->cropMarker = $cropMarker;

        return $this;
    }

    public function setHighlightPreTag(string $highlightPreTag): SearchQuery
    {
        $this->highlightPreTag = $highlightPreTag;

        return $this;
    }

    public function setHighlightPostTag(string $highlightPostTag): SearchQuery
    {
        $this->highlightPostTag = $highlightPostTag;

        return $this;
    }

    public function setFacets(array $facets): SearchQuery
    {
        $this->facets = $facets;

        return $this;
    }

    public function setShowMatchesPosition(?bool $showMatchesPosition): SearchQuery
    {
        $this->showMatchesPosition = $showMatchesPosition;

        return $this;
    }

    public function setShowRankingScore(?bool $showRankingScore): SearchQuery
    {
        $this->showRankingScore = $showRankingScore;

        return $this;
    }

    /**
     * @param bool $showRankingScoreDetails whether the feature is enabled or not
     */
    public function setShowRankingScoreDetails(?bool $showRankingScoreDetails): SearchQuery
    {
        $this->showRankingScoreDetails = $showRankingScoreDetails;

        return $this;
    }

    public function setSort(array $sort): SearchQuery
    {
        $this->sort = $sort;

        return $this;
    }

    public function setMatchingStrategy(string $matchingStrategy): SearchQuery
    {
        $this->matchingStrategy = $matchingStrategy;

        return $this;
    }

    public function setOffset(?int $offset): SearchQuery
    {
        $this->offset = $offset;

        return $this;
    }

    public function setLimit(?int $limit): SearchQuery
    {
        $this->limit = $limit;

        return $this;
    }

    public function setHitsPerPage(?int $hitsPerPage): SearchQuery
    {
        $this->hitsPerPage = $hitsPerPage;

        return $this;
    }

    public function setPage(?int $page): SearchQuery
    {
        $this->page = $page;

        return $this;
    }

    /**
     * @param list<float|list<float>> $vector a multi-level array floats
     */
    public function setVector(array $vector): SearchQuery
    {
        $this->vector = $vector;

        return $this;
    }

    /**
     * @param list<non-empty-string> $attributesToSearchOn
     */
    public function setAttributesToSearchOn(array $attributesToSearchOn): SearchQuery
    {
        $this->attributesToSearchOn = $attributesToSearchOn;

        return $this;
    }

    /**
     * @internal
     */
    public function toEngineQuery(string $prefix, array $indices): EngineQuery
    {
        $query = new EngineQuery();
        foreach ($indices as $indice) {
            if ($indice['class'] === $this->className) {
                $query->setIndexUid("$prefix{$indice['name']}");

                break;
            }
        }
        if (null !== $this->q) {
            $query->setQuery($this->q);
        }

        // @todo: set all data

        return $query;
    }
}
