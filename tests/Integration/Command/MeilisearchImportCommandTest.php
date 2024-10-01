<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration\Command;

use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Bundle\Tests\Entity\DummyCustomGroups;
use Meilisearch\Bundle\Tests\Entity\DynamicSettings;
use Meilisearch\Bundle\Tests\Entity\SelfNormalizable;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Search\SearchResult;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class MeilisearchImportCommandTest extends BaseKernelTestCase
{
    private static string $indexName = 'posts';

    private Application $application;
    private Indexes $index;

    protected function setUp(): void
    {
        parent::setUp();

        $this->index = $this->client->index($this->getPrefix().self::$indexName);
        $this->application = new Application(self::createKernel());
    }

    public function testImportWithoutUpdatingSettings(): void
    {
        for ($i = 0; $i <= 5; ++$i) {
            $this->createPost();
        }

        $importCommand = $this->application->find('meilisearch:import');
        $importCommandTester = new CommandTester($importCommand);
        $importCommandTester->execute(['--indices' => 'posts', '--no-update-settings' => true]);

        $importOutput = $importCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Importing for index Meilisearch\Bundle\Tests\Entity\Post
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__posts index (6 indexed since start)
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__aggregated index (6 indexed since start)
Done!

EOD, $importOutput);
    }

    public function testImportContentItem(): void
    {
        for ($i = 0; $i <= 5; ++$i) {
            $this->createArticle();
        }

        for ($i = 0; $i <= 5; ++$i) {
            $this->createPodcast();
        }

        $importCommand = $this->application->find('meilisearch:import');
        $importCommandTester = new CommandTester($importCommand);
        $importCommandTester->execute(['--indices' => 'discriminator_map', '--no-update-settings' => true]);

        $this->assertSame(<<<'EOD'
Importing for index Meilisearch\Bundle\Tests\Entity\ContentItem
Indexed a batch of 12 / 12 Meilisearch\Bundle\Tests\Entity\ContentItem entities into sf_phpunit__discriminator_map index (12 indexed since start)
Done!

EOD, $importCommandTester->getDisplay());
    }

    public function testSearchImportWithCustomBatchSize(): void
    {
        for ($i = 0; $i <= 10; ++$i) {
            $this->createPage($i);
        }

        $importCommand = $this->application->find('meilisearch:import');
        $importCommandTester = new CommandTester($importCommand);
        $importCommandTester->execute([
            '--indices' => 'pages',
            '--batch-size' => '2',
        ]);

        $this->assertSame(<<<'EOD'
Importing for index Meilisearch\Bundle\Tests\Entity\Page
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index (2 indexed since start)
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index (4 indexed since start)
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index (6 indexed since start)
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index (8 indexed since start)
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index (10 indexed since start)
Indexed a batch of 1 / 1 Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index (11 indexed since start)
Done!

EOD, $importCommandTester->getDisplay());
    }

    public function testSearchImportWithCustomResponseTimeout(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $this->createPage($i);
        }

        $importCommand = $this->application->find('meilisearch:import');
        $importCommandTester = new CommandTester($importCommand);
        $return = $importCommandTester->execute([
            '--indices' => 'pages',
            '--response-timeout' => 10000,
        ]);
        $output = $importCommandTester->getDisplay();

        $this->assertStringContainsString('Importing for index Meilisearch\Bundle\Tests\Entity\Page', $output);
        $this->assertStringContainsString('Indexed a batch of '.$i.' / '.$i.' Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index ('.$i.' indexed since start)', $output);
        $this->assertStringContainsString('Done!', $output);
        $this->assertSame(0, $return);

        // Reset all
        parent::setUp();

        for ($i = 0; $i < 10; ++$i) {
            $this->createPage($i);
        }

        // test if it will work with a bad option
        $importCommand = $this->application->find('meilisearch:import');
        $importCommandTester = new CommandTester($importCommand);
        $return = $importCommandTester->execute([
            '--indices' => 'pages',
            '--response-timeout' => 'asd',
        ]);
        $output = $importCommandTester->getDisplay();

        $this->assertStringContainsString('Importing for index Meilisearch\Bundle\Tests\Entity\Page', $output);
        $this->assertStringContainsString('Indexed a batch of '.$i.' / '.$i.' Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index ('.$i.' indexed since start)', $output);
        $this->assertStringContainsString('Done!', $output);
        $this->assertSame(0, $return);
    }

    /**
     * Importing 'Tag' and 'Link' into the same 'tags' index.
     */
    public function testImportDifferentEntitiesIntoSameIndex(): void
    {
        for ($i = 0; $i <= 5; ++$i) {
            $this->createTag(['id' => $i]);
        }
        $this->createLink(['id' => 60, 'isSponsored' => true]);
        $this->createLink(['id' => 61, 'isSponsored' => true]);

        $command = $this->application->find('meilisearch:import');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--indices' => 'tags',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Importing for index Meilisearch\Bundle\Tests\Entity\Tag', $output);
        $this->assertStringContainsString('Indexed a batch of '.$i.' / '.$i.' Meilisearch\Bundle\Tests\Entity\Tag entities into sf_phpunit__tags index ('.$i.' indexed since start)', $output);
        $this->assertStringContainsString('Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Link entities into sf_phpunit__tags index (2 indexed since start)', $output);
        $this->assertStringContainsString('Done!', $output);

        /** @var SearchResult $searchResult */
        $searchResult = $this->client->index($this->getPrefix().'tags')->search('Test');
        $this->assertCount(8, $searchResult->getHits());
        $this->assertSame(8, $searchResult->getHitsCount());
    }

    public function testSearchImportAggregator(): void
    {
        for ($i = 0; $i <= 5; ++$i) {
            $this->createPost();
        }

        $command = $this->application->find('meilisearch:import');
        $commandTester = new CommandTester($command);
        $return = $commandTester->execute([
            '--indices' => $this->index->getUid(),
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Importing for index Meilisearch\Bundle\Tests\Entity\Post', $output);
        $this->assertStringContainsString('Indexed a batch of '.$i.' / '.$i.' Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__'.self::$indexName.' index ('.$i.' indexed since start)', $output);
        $this->assertStringContainsString('Done!', $output);
        $this->assertSame(0, $return);
    }

    public function testSearchImportWithSkipBatches(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $this->createPage($i);
        }

        $command = $this->application->find('meilisearch:import');
        $commandTester = new CommandTester($command);
        $return = $commandTester->execute([
            '--indices' => 'pages',
            '--batch-size' => '3',
            '--skip-batches' => '2',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Importing for index Meilisearch\Bundle\Tests\Entity\Page', $output);
        $this->assertStringContainsString('Skipping first 2 batches (6 records)', $output);
        $this->assertStringContainsString('Indexed a batch of 3 / 3 Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index (3 indexed since start)', $output);
        $this->assertStringContainsString('Indexed a batch of 1 / 1 Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index (4 indexed since start)', $output);
        $this->assertStringContainsString('Done!', $output);
        $this->assertSame(0, $return);
    }

    public function testImportingIndexNameWithAndWithoutPrefix(): void
    {
        for ($i = 0; $i <= 5; ++$i) {
            $this->createPost();
        }

        $command = $this->application->find('meilisearch:import');
        $commandTester = new CommandTester($command);
        $return = $commandTester->execute([
            '--indices' => $this->index->getUid(), // This is the already prefixed name
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Importing for index Meilisearch\Bundle\Tests\Entity\Post', $output);
        $this->assertStringContainsString('Indexed a batch of '.$i.' / '.$i.' Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__'.self::$indexName.' index ('.$i.' indexed since start)', $output);
        $this->assertStringContainsString('Done!', $output);
        $this->assertSame(0, $return);

        // Reset database and MS indexes
        parent::setUp();

        for ($i = 0; $i <= 5; ++$i) {
            $this->createPost();
        }

        $command = $this->application->find('meilisearch:import');
        $commandTester = new CommandTester($command);
        $return = $commandTester->execute([
            '--indices' => self::$indexName, // This is the already prefixed name
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Importing for index Meilisearch\Bundle\Tests\Entity\Post', $output);
        $this->assertStringContainsString('Indexed a batch of '.$i.' / '.$i.' Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__'.self::$indexName.' index ('.$i.' indexed since start)', $output);
        $this->assertStringContainsString('Done!', $output);
        $this->assertSame(0, $return);
    }

    public function testImportsSelfNormalizable(): void
    {
        for ($i = 1; $i <= 2; ++$i) {
            $this->entityManager->persist(new SelfNormalizable($i, "Self normalizabie $i"));
        }

        $this->entityManager->flush();

        $importCommand = $this->application->find('meilisearch:import');
        $importCommandTester = new CommandTester($importCommand);
        $importCommandTester->execute(['--indices' => 'self_normalizable']);

        $importOutput = $importCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Importing for index Meilisearch\Bundle\Tests\Entity\SelfNormalizable
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\SelfNormalizable entities into sf_phpunit__self_normalizable index (2 indexed since start)
Done!

EOD, $importOutput);

        self::assertSame([
            [
                'objectID' => 1,
                'id' => 1,
                'name' => 'this test is correct',
                'self_normalized' => true,
            ],
            [
                'objectID' => 2,
                'id' => 2,
                'name' => 'this test is correct',
                'self_normalized' => true,
            ],
        ], $this->client->index('sf_phpunit__self_normalizable')->getDocuments()->getResults());
    }

    public function testImportsDummyWithCustomGroups(): void
    {
        for ($i = 1; $i <= 2; ++$i) {
            $this->entityManager->persist(new DummyCustomGroups($i, "Dummy $i", new \DateTimeImmutable('2024-04-04 07:32:0'.$i)));
        }

        $this->entityManager->flush();

        $importCommand = $this->application->find('meilisearch:import');
        $importCommandTester = new CommandTester($importCommand);
        $importCommandTester->execute(['--indices' => 'dummy_custom_groups']);

        $importOutput = $importCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Importing for index Meilisearch\Bundle\Tests\Entity\DummyCustomGroups
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\DummyCustomGroups entities into sf_phpunit__dummy_custom_groups index (2 indexed since start)
Done!

EOD, $importOutput);

        self::assertSame([
            [
                'objectID' => 1,
                'id' => 1,
                'name' => 'Dummy 1',
                'createdAt' => 1712215921,
            ],
            [
                'objectID' => 2,
                'id' => 2,
                'name' => 'Dummy 2',
                'createdAt' => 1712215922,
            ],
        ], $this->client->index('sf_phpunit__dummy_custom_groups')->getDocuments()->getResults());
    }

    public function testImportWithDynamicSettings(): void
    {
        for ($i = 0; $i <= 5; ++$i) {
            $this->entityManager->persist(new DynamicSettings($i, "Dynamic $i"));
        }

        $this->entityManager->flush();

        $importCommand = $this->application->find('meilisearch:import');
        $importCommandTester = new CommandTester($importCommand);
        $importCommandTester->execute(['--indices' => 'dynamic_settings']);

        $importOutput = $importCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Importing for index Meilisearch\Bundle\Tests\Entity\DynamicSettings
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\DynamicSettings entities into sf_phpunit__dynamic_settings index (6 indexed since start)
Setting "filterableAttributes" updated of "sf_phpunit__dynamic_settings".
Setting "searchableAttributes" updated of "sf_phpunit__dynamic_settings".
Setting "stopWords" updated of "sf_phpunit__dynamic_settings".
Setting "synonyms" updated of "sf_phpunit__dynamic_settings".
Done!

EOD, $importOutput);

        $settings = $this->get('meilisearch.client')->index('sf_phpunit__dynamic_settings')->getSettings();

        $getSetting = static fn ($value) => $value instanceof \IteratorAggregate ? iterator_to_array($value) : $value;

        self::assertSame(['publishedAt', 'title'], $getSetting($settings['filterableAttributes']));
        self::assertSame(['title'], $getSetting($settings['searchableAttributes']));
        self::assertSame(['a', 'n', 'the'], $getSetting($settings['stopWords']));
        self::assertSame(['fantastic' => ['great'], 'great' => ['fantastic']], $getSetting($settings['synonyms']));
    }

    public function testImportUpdatesSettingsOnce(): void
    {
        for ($i = 0; $i <= 3; ++$i) {
            $this->createPost();
        }

        $this->entityManager->flush();

        $importCommand = $this->application->find('meilisearch:import');
        $importCommandTester = new CommandTester($importCommand);
        $importCommandTester->execute(['--indices' => 'posts', '--batch-size' => '2']);

        $importOutput = $importCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Importing for index Meilisearch\Bundle\Tests\Entity\Post
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__posts index (2 indexed since start)
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__aggregated index (2 indexed since start)
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__posts index (4 indexed since start)
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__aggregated index (4 indexed since start)
Setting "stopWords" updated of "sf_phpunit__posts".
Setting "filterableAttributes" updated of "sf_phpunit__posts".
Setting "searchCutoffMs" updated of "sf_phpunit__posts".
Setting "typoTolerance" updated of "sf_phpunit__posts".
Done!

EOD, $importOutput);
    }

    public function testAlias(): void
    {
        $command = $this->application->find('meilisearch:import');

        self::assertSame(['meili:import'], $command->getAliases());
    }

    public function testImportingIndexWithSwap(): void
    {
        for ($i = 0; $i <= 5; ++$i) {
            $this->createPost();
        }

        $command = $this->application->find('meilisearch:import');
        $commandTester = new CommandTester($command);
        $return = $commandTester->execute([
            '--swap-indices' => true,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Importing for index Meilisearch\Bundle\Tests\Entity\Post', $output);
        $this->assertStringContainsString('Indexed a batch of '.$i.' / '.$i.' Meilisearch\Bundle\Tests\Entity\Post entities into _tmp_sf_phpunit__'.self::$indexName.' index ('.$i.' indexed since start)', $output);
        $this->assertStringContainsString('Swapping indices...', $output);
        $this->assertStringContainsString('Indices swapped.', $output);
        $this->assertStringContainsString('Deleting temporary indices...', $output);
        $this->assertStringContainsString('Deleted _tmp_sf_phpunit__'.self::$indexName, $output);
        $this->assertStringContainsString('Done!', $output);
        $this->assertSame(0, $return);
    }

    public function testImportingIndexWithSwapAndTempIndexPrefix(): void
    {
        for ($i = 0; $i <= 5; ++$i) {
            $this->createPost();
        }

        $command = $this->application->find('meilisearch:import');
        $commandTester = new CommandTester($command);
        $return = $commandTester->execute([
            '--swap-indices' => true,
            '--temp-index-prefix' => '_temp_prefix_',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Importing for index Meilisearch\Bundle\Tests\Entity\Post', $output);
        $this->assertStringContainsString('Indexed a batch of '.$i.' / '.$i.' Meilisearch\Bundle\Tests\Entity\Post entities into _temp_prefix_sf_phpunit__'.self::$indexName.' index ('.$i.' indexed since start)', $output);
        $this->assertStringContainsString('Swapping indices...', $output);
        $this->assertStringContainsString('Indices swapped.', $output);
        $this->assertStringContainsString('Deleting temporary indices...', $output);
        $this->assertStringContainsString('Deleted _temp_prefix_sf_phpunit__'.self::$indexName, $output);
        $this->assertStringContainsString('Done!', $output);
        $this->assertSame(0, $return);
    }
}
