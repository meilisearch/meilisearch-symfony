<?php

namespace MeiliSearch\Bundle\Command;

use MeiliSearch\Bundle\SearchService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function array_keys;
use function count;
use function explode;

/**
 * Class IndexCommand.
 *
 * @package MeiliSearch\Bundle\Command
 */
abstract class IndexCommand extends Command
{
    /** @var SearchService */
    protected $searchService;

    /**
     * IndexCommand constructor.
     *
     * @param SearchService $searchService
     * @param string|null   $name
     */
    public function __construct(SearchService $searchService, string $name = null)
    {
        $this->searchService = $searchService;
        parent::__construct($name);
    }

    protected function getEntitiesFromArgs(InputInterface $input, OutputInterface $output): array
    {
        $entities = [];
        $indexNames = [];

        if ($indexList = $input->getOption('indices')) {
            $indexNames = explode(',', $indexList);
        }

        $config = $this->searchService->getConfiguration();

        if ((0 === count($indexNames))
            && !empty(array_keys($config['indices']))) {
            $indexNames = array_keys($config['indices']);
        }

        if (0 === count($indexNames)) {
            $output->writeln(
                '<comment>No indices specified. Please either specify indices using the cli option or YAML configuration.</comment>'
            );
        }

        foreach ($indexNames as $name) {
            if (isset($config['indices'][$name])) {
                $entities[$name]['name'] = $config['indices'][$name]['class'];
                if (true === $input->hasOption('update-settings') && !empty($config['indices'][$name]['settings'])) {
                    $entities[$name]['settings'] = $config['indices'][$name]['settings'];
                }
            } else {
                $output->writeln(
                    '<comment>No index named <info>'.$name.'</info> was found. Check you configuration.</comment>'
                );
            }
        }

        return $entities;
    }
}
