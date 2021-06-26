<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Command;

use Illuminate\Support\Collection;
use MeiliSearch\Bundle\SearchService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IndexCommand.
 */
abstract class IndexCommand extends Command
{
    private string $prefix;
    protected SearchService $searchService;

    public function __construct(SearchService $searchService, ?string $name = null)
    {
        $this->searchService = $searchService;
        $this->prefix = $this->searchService->getConfiguration()->get('prefix');

        parent::__construct($name);
    }

    protected function getIndices(): Collection
    {
        return collect($this->searchService->getConfiguration()->get('indices'))
            ->transform(function (array $item) {
                $item['name'] = $this->prefix.$item['name'];

                return $item;
            });
    }

    protected function getEntitiesFromArgs(InputInterface $input, OutputInterface $output): Collection
    {
        $indexNames = collect();

        if ($indexList = $input->getOption('indices')) {
            $list = \explode(',', $indexList);
            $indexNames = collect($list)->transform(function (string $item): string {
                // Check if the given index name already contains the prefix
                if (false === strpos($item, $this->prefix)) {
                    return $this->prefix.$item;
                }

                return $item;
            });
        }

        $config = $this->searchService->getConfiguration();

        if ((0 === count($indexNames)) && count($config->get('indices')) > 0) {
            $indexNames = $this->getIndices();
        }

        if (0 === count($indexNames)) {
            $output->writeln(
                '<comment>No indices specified. Please either specify indices using the cli option or YAML configuration.</comment>'
            );
        }

        return collect($this->getIndices())->reject(fn (array $item) => !in_array($item['name'], $indexNames->toArray(), true));
    }
}
