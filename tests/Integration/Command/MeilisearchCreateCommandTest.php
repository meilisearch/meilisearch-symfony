<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration\Command;

use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Bundle\Tests\Entity\DynamicSettings;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class MeilisearchCreateCommandTest extends BaseKernelTestCase
{
    protected Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->application = new Application(self::createKernel());
    }

    public function testExecuteIndexCreation(): void
    {
        $createCommand = $this->application->find('meilisearch:create');
        $createCommandTester = new CommandTester($createCommand);
        $createCommandTester->execute([]);

        $this->assertSame($this->client->getTasks()->getResults()[0]['type'], 'indexCreation');
    }

    /**
     * @testWith [false]
     *           [true]
     */
    public function testWithoutIndices(bool $updateSettings): void
    {
        $createCommand = $this->application->find('meilisearch:create');
        $createCommandTester = new CommandTester($createCommand);
        $createCommandTester->execute($updateSettings ? [] : ['--no-update-settings' => true]);

        $createOutput = $createCommandTester->getDisplay();

        if ($updateSettings) {
            $this->assertSame(<<<'EOD'
Creating index sf_phpunit__posts for Meilisearch\Bundle\Tests\Entity\Post
Settings updated of "sf_phpunit__posts".
Settings updated of "sf_phpunit__posts".
Settings updated of "sf_phpunit__posts".
Settings updated of "sf_phpunit__posts".
Creating index sf_phpunit__comments for Meilisearch\Bundle\Tests\Entity\Comment
Creating index sf_phpunit__tags for Meilisearch\Bundle\Tests\Entity\Tag
Creating index sf_phpunit__tags for Meilisearch\Bundle\Tests\Entity\Link
Creating index sf_phpunit__discriminator_map for Meilisearch\Bundle\Tests\Entity\ContentItem
Creating index sf_phpunit__pages for Meilisearch\Bundle\Tests\Entity\Page
Creating index sf_phpunit__self_normalizable for Meilisearch\Bundle\Tests\Entity\SelfNormalizable
Creating index sf_phpunit__dummy_custom_groups for Meilisearch\Bundle\Tests\Entity\DummyCustomGroups
Creating index sf_phpunit__dynamic_settings for Meilisearch\Bundle\Tests\Entity\DynamicSettings
Settings updated of "sf_phpunit__dynamic_settings".
Settings updated of "sf_phpunit__dynamic_settings".
Settings updated of "sf_phpunit__dynamic_settings".
Settings updated of "sf_phpunit__dynamic_settings".
Creating index sf_phpunit__aggregated for Meilisearch\Bundle\Tests\Entity\Post
Creating index sf_phpunit__aggregated for Meilisearch\Bundle\Tests\Entity\Tag
Done!

EOD, $createOutput);
        } else {
            $this->assertSame(<<<'EOD'
Creating index sf_phpunit__posts for Meilisearch\Bundle\Tests\Entity\Post
Creating index sf_phpunit__comments for Meilisearch\Bundle\Tests\Entity\Comment
Creating index sf_phpunit__tags for Meilisearch\Bundle\Tests\Entity\Tag
Creating index sf_phpunit__tags for Meilisearch\Bundle\Tests\Entity\Link
Creating index sf_phpunit__discriminator_map for Meilisearch\Bundle\Tests\Entity\ContentItem
Creating index sf_phpunit__pages for Meilisearch\Bundle\Tests\Entity\Page
Creating index sf_phpunit__self_normalizable for Meilisearch\Bundle\Tests\Entity\SelfNormalizable
Creating index sf_phpunit__dummy_custom_groups for Meilisearch\Bundle\Tests\Entity\DummyCustomGroups
Creating index sf_phpunit__dynamic_settings for Meilisearch\Bundle\Tests\Entity\DynamicSettings
Creating index sf_phpunit__aggregated for Meilisearch\Bundle\Tests\Entity\Post
Creating index sf_phpunit__aggregated for Meilisearch\Bundle\Tests\Entity\Tag
Done!

EOD, $createOutput);
        }
    }

    public function testWithIndices(): void
    {
        $createCommand = $this->application->find('meilisearch:create');
        $createCommandTester = new CommandTester($createCommand);
        $createCommandTester->execute([
            '--indices' => 'posts',
        ]);

        $createOutput = $createCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Creating index sf_phpunit__posts for Meilisearch\Bundle\Tests\Entity\Post
Settings updated of "sf_phpunit__posts".
Settings updated of "sf_phpunit__posts".
Settings updated of "sf_phpunit__posts".
Settings updated of "sf_phpunit__posts".
Done!

EOD, $createOutput);
    }

    public function testWithDynamicSettings(): void
    {
        for ($i = 0; $i <= 5; ++$i) {
            $this->entityManager->persist(new DynamicSettings($i, "Dynamic $i"));
        }

        $this->entityManager->flush();

        $importCommand = $this->application->find('meilisearch:create');
        $importCommandTester = new CommandTester($importCommand);
        $importCommandTester->execute(['--indices' => 'dynamic_settings']);

        $importOutput = $importCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Creating index sf_phpunit__dynamic_settings for Meilisearch\Bundle\Tests\Entity\DynamicSettings
Settings updated of "sf_phpunit__dynamic_settings".
Settings updated of "sf_phpunit__dynamic_settings".
Settings updated of "sf_phpunit__dynamic_settings".
Settings updated of "sf_phpunit__dynamic_settings".
Done!

EOD, $importOutput);

        $settings = $this->get('meilisearch.client')->index('sf_phpunit__dynamic_settings')->getSettings();

        $getSetting = static fn ($value) => $value instanceof \IteratorAggregate ? iterator_to_array($value) : $value;

        self::assertSame(['publishedAt', 'title'], $getSetting($settings['filterableAttributes']));
        self::assertSame(['title'], $getSetting($settings['searchableAttributes']));
        self::assertSame(['a', 'n', 'the'], $getSetting($settings['stopWords']));
        self::assertSame(['fantastic' => ['great'], 'great' => ['fantastic']], $getSetting($settings['synonyms']));
    }

    public function testAlias(): void
    {
        $command = $this->application->find('meilisearch:create');

        self::assertSame(['meili:create'], $command->getAliases());
    }
}
