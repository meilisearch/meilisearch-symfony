<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration\Command;

use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Bundle\Tests\Entity\Post;
use Meilisearch\Contracts\TasksQuery;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class MeilisearchCompactCommandTest extends BaseKernelTestCase
{
    protected Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->application = new Application(self::createKernel());
    }

    public function testCompactWithIndices(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $this->entityManager->persist(new Post());
        }

        $this->entityManager->flush();

        $this->waitForAllTasks();

        $succeededCompactionsBefore = $this->countSucceededCompactions($this->getPrefix().'posts');

        $compactCommand = $this->application->find('meilisearch:compact');
        $compactCommandTester = new CommandTester($compactCommand);
        $compactCommandTester->execute(['--indices' => 'posts']);

        $compactCommandTester->assertCommandIsSuccessful();

        $this->assertSame(<<<'EOD'
Compacted sf_phpunit__posts
Done!

EOD, $compactCommandTester->getDisplay());

        $this->assertSame(
            $succeededCompactionsBefore + 1,
            $this->countSucceededCompactions($this->getPrefix().'posts')
        );
    }

    public function testCompactIndexSharedByEntitiesOnlyOnce(): void
    {
        $createCommand = $this->application->find('meilisearch:create');
        $createCommandTester = new CommandTester($createCommand);
        $createCommandTester->execute(['--indices' => 'tags']);

        $this->waitForAllTasks();

        $compactCommand = $this->application->find('meilisearch:compact');
        $compactCommandTester = new CommandTester($compactCommand);
        $compactCommandTester->execute(['--indices' => 'tags']);

        $compactCommandTester->assertCommandIsSuccessful();

        $this->assertSame(<<<'EOD'
Compacted sf_phpunit__tags
Done!

EOD, $compactCommandTester->getDisplay());
    }

    public function testCompactReportsIndexMissingOnServer(): void
    {
        $this->waitForAllTasks();

        $compactCommand = $this->application->find('meilisearch:compact');
        $compactCommandTester = new CommandTester($compactCommand);
        $compactCommandTester->execute(['--indices' => 'tags']);

        $compactCommandTester->assertCommandIsSuccessful();

        $this->assertSame(<<<'EOD'
Index sf_phpunit__tags couldn't be compacted: Index `sf_phpunit__tags` not found.
Done!

EOD, $compactCommandTester->getDisplay());
    }

    public function testCompactUnknownIndex(): void
    {
        $compactCommand = $this->application->find('meilisearch:compact');
        $compactCommandTester = new CommandTester($compactCommand);
        $compactCommandTester->execute(['--indices' => 'unknown']);

        $compactCommandTester->assertCommandIsSuccessful();

        $this->assertSame(<<<'EOD'
Cannot compact index. Not found.
Done!

EOD, $compactCommandTester->getDisplay());
    }

    public function testOptions(): void
    {
        $definition = $this->application->find('meilisearch:compact')->getDefinition();

        $this->assertSame('i', $definition->getOption('indices')->getShortcut());
        $this->assertSame('t', $definition->getOption('response-timeout')->getShortcut());
        $this->assertSame(5000, $definition->getOption('response-timeout')->getDefault());
    }

    public function testAlias(): void
    {
        $command = $this->application->find('meilisearch:compact');

        self::assertSame(['meili:compact'], $command->getAliases());
    }

    private function countSucceededCompactions(string $indexUid): int
    {
        $query = (new TasksQuery())
            ->setTypes(['indexCompaction'])
            ->setStatuses(['succeeded'])
            ->setIndexUids([$indexUid]);

        return $this->client->getTasks($query)->getTotal();
    }
}
