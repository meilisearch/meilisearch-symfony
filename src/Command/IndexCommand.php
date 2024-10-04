<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Command;

use Meilisearch\Bundle\Collection;
use Meilisearch\Bundle\SearchService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class IndexCommand extends Command
{
    protected const DEFAULT_RESPONSE_TIMEOUT = 5000;

    protected SearchService $searchService;

    private string $prefix;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
        $this->prefix = $this->searchService->getConfiguration()->get('prefix');

        parent::__construct();
    }

    protected function getEntitiesFromArgs(InputInterface $input, OutputInterface $output): Collection
    {
        $indices = new Collection($this->searchService->getConfiguration()->get('indices'));
        $indexNames = new Collection();

        if ($indexList = $input->getOption('indices')) {
            $list = explode(',', $indexList);
            $indexNames = (new Collection($list))->transform(function (string $item): string {
                // Check if the given index name already contains the prefix
                if (!str_contains($item, $this->prefix)) {
                    return $this->prefix.$item;
                }

                return $item;
            });
        }

        if (0 === \count($indexNames) && 0 === \count($indices)) {
            $output->writeln(
                '<comment>No indices specified. Please either specify indices using the cli option or YAML configuration.</comment>'
            );

            return new Collection();
        }

        if (\count($indexNames) > 0) {
            return $indices->reject(fn (array $item) => !\in_array($item['prefixed_name'], $indexNames->all(), true));
        }

        return $indices;
    }
}
