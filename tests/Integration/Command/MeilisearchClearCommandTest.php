<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration\Command;

use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class MeilisearchClearCommandTest extends BaseKernelTestCase
{
    protected Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->application = new Application(self::createKernel());
    }

    public function testClear(): void
    {
        $command = $this->application->find('meilisearch:clear');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertSame(<<<'EOD'
Cleared sf_phpunit__posts index of Meilisearch\Bundle\Tests\Entity\Post
Cleared sf_phpunit__comments index of Meilisearch\Bundle\Tests\Entity\Comment
Cleared sf_phpunit__aggregated index of Meilisearch\Bundle\Tests\Entity\ContentAggregator
Cleared sf_phpunit__tags index of Meilisearch\Bundle\Tests\Entity\Tag
Cleared sf_phpunit__tags index of Meilisearch\Bundle\Tests\Entity\Link
Cleared sf_phpunit__discriminator_map index of Meilisearch\Bundle\Tests\Entity\ContentItem
Cleared sf_phpunit__pages index of Meilisearch\Bundle\Tests\Entity\Page
Cleared sf_phpunit__self_normalizable index of Meilisearch\Bundle\Tests\Entity\SelfNormalizable
Cleared sf_phpunit__dummy_custom_groups index of Meilisearch\Bundle\Tests\Entity\DummyCustomGroups
Cleared sf_phpunit__dynamic_settings index of Meilisearch\Bundle\Tests\Entity\DynamicSettings
Done!

EOD, $commandTester->getDisplay());
    }

    public function testClearWithIndice(): void
    {
        $command = $this->application->find('meilisearch:clear');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--indices' => 'posts']);

        $this->assertSame(<<<'EOD'
Cleared sf_phpunit__posts index of Meilisearch\Bundle\Tests\Entity\Post
Done!

EOD, $commandTester->getDisplay());
    }

    public function testClearUnknownIndex(): void
    {
        $command = $this->application->find('meilisearch:clear');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--indices' => 'test',
        ]);

        $this->assertStringContainsString('Cannot clear index. Not found.', $commandTester->getDisplay());
    }

    public function testAlias(): void
    {
        $command = $this->application->find('meilisearch:clear');

        self::assertSame(['meili:clear'], $command->getAliases());
    }
}
