<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Command;

use Meilisearch\Bundle\Engine;
use Meilisearch\Bundle\SearchManagerInterface;
use Meilisearch\Contracts\TaskStatus;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Exceptions\TimeOutException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'meilisearch:compact', description: 'Compact the indexes', aliases: ['meili:compact'])]
final class MeilisearchCompactCommand extends IndexCommand
{
    public function __construct(
        SearchManagerInterface $searchManager,
        private readonly Engine $engine,
    ) {
        parent::__construct($searchManager);
    }

    protected function configure(): void
    {
        $this
            ->addOption('indices', 'i', InputOption::VALUE_OPTIONAL, 'Comma-separated list of index names')
            ->addOption(
                'response-timeout',
                't',
                InputOption::VALUE_REQUIRED,
                'Timeout (in ms) to get response from the search engine',
                self::DEFAULT_RESPONSE_TIMEOUT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexToCompact = $this->getEntitiesFromArgs($input, $output)->unique('prefixed_name');
        $responseTimeout = ((int) $input->getOption('response-timeout')) ?: self::DEFAULT_RESPONSE_TIMEOUT;

        /** @var array<string, mixed> $index */
        foreach ($indexToCompact as $index) {
            $indexName = $index['prefixed_name'];

            try {
                $task = $this->engine->compact($indexName)->wait($responseTimeout);
            } catch (ApiException $e) {
                $output->writeln('Cannot compact '.$indexName.': '.$e->getMessage());

                continue;
            } catch (TimeOutException) {
                $output->writeln('<comment>Timed out waiting for the compaction of <info>'.$indexName.'</info>, the task is still running on the server</comment>');

                continue;
            }

            if (TaskStatus::Failed === $task->getStatus()) {
                $output->writeln('<error>Index <info>'.$indexName.'</info> couldn\'t be compacted: '.$task->getError()?->message.'</error>');

                continue;
            }

            $output->writeln('Compacted <info>'.$indexName.'</info>');
        }

        if (0 === \count($indexToCompact)) {
            $output->writeln('Cannot compact index. Not found.');
        }

        $output->writeln('<info>Done!</info>');

        return 0;
    }
}
