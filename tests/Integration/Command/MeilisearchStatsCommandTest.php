<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration\Command;

use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Bundle\Tests\Entity\Post;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class MeilisearchStatsCommandTest extends BaseKernelTestCase
{
    protected Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->application = new Application(self::createKernel());
    }

    public function testGlobalStats(): void
    {
        $statsCommand = $this->application->find('meilisearch:stats');
        $statsCommandTester = new CommandTester($statsCommand);
        $statsCommandTester->execute([]);

        $statsCommandTester->assertCommandIsSuccessful();

        $statsOutput = $statsCommandTester->getDisplay();

        $this->assertMatchesRegularExpression('/^\+[-+]* Global stats [-+]*\+$/m', $statsOutput);
        $this->assertStringContainsString('| Database size | Used database size | Fragmentation ratio | Last update ', $statsOutput);
        $this->assertMatchesRegularExpression(
            '/^\| [\d,]+(\.\d{2} [KMGT]B| B) +\| [\d,]+(\.\d{2} [KMGT]B| B) +\| \d+\.\d{2}% +\| (\d{4} [A-Z][a-z]{2} \d{2}, \d{2}:\d{2}:\d{2}\.\d{6}|-) +\|$/m',
            $statsOutput
        );
    }

    public function testIndexesStatsWithIndices(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $this->entityManager->persist(new Post());
        }

        $this->entityManager->flush();

        $this->waitForAllTasks();

        $statsCommand = $this->application->find('meilisearch:stats');

        $this->assertTrue($statsCommand->getDefinition()->hasOption('indices'));

        $statsCommandTester = new CommandTester($statsCommand);
        $statsCommandTester->execute(['--indices' => 'posts']);

        $statsCommandTester->assertCommandIsSuccessful();

        $statsOutput = $statsCommandTester->getDisplay();

        $this->assertMatchesRegularExpression('/^\+[-+]* Indexes stats [-+]*\+$/m', $statsOutput);
        $this->assertMatchesRegularExpression(
            '/^\| Index +\| No of documents +\| Document db size +\| Avg document size +\| Fragmentation ratio +\| Indexing +\| No of embeddings +\| No of embedded documents +\|$/m',
            $statsOutput
        );
        $this->assertMatchesRegularExpression(
            '/^\| sf_phpunit__posts +\| 3 +\| [\d,]+(\.\d{2} [KMGT]B| B) +\| [\d,]+(\.\d{2} [KMGT]B| B) +\| \d+\.\d{2}% +\| (Yes|No) +\| 0 +\| 0 +\|$/m',
            $statsOutput
        );
        $this->assertStringNotContainsString('sf_phpunit__aggregated', $statsOutput);
    }

    public function testAlias(): void
    {
        $command = $this->application->find('meilisearch:stats');

        self::assertSame(['meili:stats'], $command->getAliases());
    }
}
