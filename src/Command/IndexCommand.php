<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Command;

use MeiliSearch\Bundle\CollectionXX;
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

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
        $this->prefix = $this->searchService->getConfiguration()->get('prefix');

        parent::__construct();
    }

    protected function getIndices(): CollectionXX
    {
        return (new CollectionXX($this->searchService->getConfiguration()->get('indices')))->transform(function (array $item) {
                $item['name'] = $this->prefix.$item['name'];

                return $item;
            });
    }

    protected function getEntitiesFromArgs(InputInterface $input, OutputInterface $output): CollectionXX
    {
        $indices = $this->getIndices();
        $indexNames = new CollectionXX();

        if ($indexList = $input->getOption('indices')) {
            $list = \explode(',', $indexList);
            $indexNames = (new CollectionXX($list))->transform(function (string $item): string {
                // Check if the given index name already contains the prefix
                if (!str_contains($item, $this->prefix)) {
                    return $this->prefix.$item;
                }

                return $item;
            });
        }

        if (0 === count($indexNames) && 0 === count($indices)) {
            $output->writeln(
                '<comment>No indices specified. Please either specify indices using the cli option or YAML configuration.</comment>'
            );

            return new CollectionXX();
        }

        if (count($indexNames) > 0) {
            foreach ($indices->getItems() as $key => $value) {
                if (!in_array($value['name'], $indexNames->toArray(), true)) {
                    unset($indices[$key]);
                }
            }
        }

        return $indices;
    }
}
