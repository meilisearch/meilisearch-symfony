<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\DataCollector;

use Meilisearch\Bundle\Debug\TraceableMeilisearchService;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Antoine Makdessi <amakdessi@me.com>
 */
final class MeilisearchDataCollector extends AbstractDataCollector
{
    private TraceableMeilisearchService $meilisearchService;

    public function __construct(TraceableMeilisearchService $meilisearchService)
    {
        $this->meilisearchService = $meilisearchService;
    }
    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
        $data = $this->meilisearchService->getData();

        $this->data[$this->getName()] = !empty($data) ? $this->cloneVar($data) : null;
    }

    public function getName(): string
    {
        return 'meilisearch';
    }

    /** @internal used in the DataCollector view template */
    public function getMeilisearch(): mixed
    {
        return $this->data[$this->getName()] ?? null;
    }
}
