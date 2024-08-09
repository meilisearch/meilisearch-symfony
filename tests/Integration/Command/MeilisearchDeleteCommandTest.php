<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration\Command;

use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class MeilisearchDeleteCommandTest extends BaseKernelTestCase
{
    protected Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->application = new Application(self::createKernel());
    }

    public function testDeleteWithoutIndices(): void
    {
        $clearCommand = $this->application->find('meilisearch:delete');
        $clearCommandTester = new CommandTester($clearCommand);
        $clearCommandTester->execute([]);

        $clearOutput = $clearCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Deleted sf_phpunit__posts
Deleted sf_phpunit__comments
Deleted sf_phpunit__aggregated
Deleted sf_phpunit__tags
Deleted sf_phpunit__discriminator_map
Deleted sf_phpunit__pages
Deleted sf_phpunit__self_normalizable
Deleted sf_phpunit__dummy_custom_groups
Deleted sf_phpunit__dynamic_settings
Deleted sf_phpunit__repository_methods
Done!

EOD, $clearOutput);
    }

    public function testDeleteWithIndices(): void
    {
        $clearCommand = $this->application->find('meilisearch:delete');
        $clearCommandTester = new CommandTester($clearCommand);
        $clearCommandTester->execute(['--indices' => 'posts']);

        $clearOutput = $clearCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Deleted sf_phpunit__posts
Done!

EOD, $clearOutput);
    }

    public function testAlias(): void
    {
        $command = $this->application->find('meilisearch:delete');

        self::assertSame(['meili:delete'], $command->getAliases());
    }
}
