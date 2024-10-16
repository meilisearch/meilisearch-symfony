<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration\Command;

use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Bundle\Tests\Entity\Post;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class MeilisearchUpdateSettingsCommandTest extends BaseKernelTestCase
{
    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->application = new Application(self::createKernel());
    }

    public function testWithoutIndices(): void
    {
        for ($i = 0; $i <= 5; ++$i) {
            $this->entityManager->persist(new Post());
        }

        $this->entityManager->flush();

        $importCommand = $this->application->find('meilisearch:update-settings');
        $importCommandTester = new CommandTester($importCommand);
        $importCommandTester->execute([]);

        $importOutput = $importCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Setting "stopWords" updated of "sf_phpunit__posts".
Setting "filterableAttributes" updated of "sf_phpunit__posts".
Setting "searchCutoffMs" updated of "sf_phpunit__posts".
Setting "typoTolerance" updated of "sf_phpunit__posts".
Setting "filterableAttributes" updated of "sf_phpunit__dynamic_settings".
Setting "searchableAttributes" updated of "sf_phpunit__dynamic_settings".
Setting "stopWords" updated of "sf_phpunit__dynamic_settings".
Setting "synonyms" updated of "sf_phpunit__dynamic_settings".
Done!

EOD, $importOutput);
    }

    public function testWithIndices(): void
    {
        for ($i = 0; $i <= 5; ++$i) {
            $this->entityManager->persist(new Post());
        }

        $this->entityManager->flush();

        $importCommand = $this->application->find('meilisearch:update-settings');
        $importCommandTester = new CommandTester($importCommand);
        $importCommandTester->execute(['--indices' => 'posts']);

        $importOutput = $importCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Setting "stopWords" updated of "sf_phpunit__posts".
Setting "filterableAttributes" updated of "sf_phpunit__posts".
Setting "searchCutoffMs" updated of "sf_phpunit__posts".
Setting "typoTolerance" updated of "sf_phpunit__posts".
Done!

EOD, $importOutput);
    }
}
