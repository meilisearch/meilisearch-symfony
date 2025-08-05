<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration\Command;

use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Bundle\Tests\Entity\Article;
use Meilisearch\Bundle\Tests\Entity\DummyCustomGroups;
use Meilisearch\Bundle\Tests\Entity\DynamicSettings;
use Meilisearch\Bundle\Tests\Entity\Link;
use Meilisearch\Bundle\Tests\Entity\ObjectId\DummyObjectId;
use Meilisearch\Bundle\Tests\Entity\Page;
use Meilisearch\Bundle\Tests\Entity\Podcast;
use Meilisearch\Bundle\Tests\Entity\Post;
use Meilisearch\Bundle\Tests\Entity\SelfNormalizable;
use Meilisearch\Bundle\Tests\Entity\Tag;
use Meilisearch\Endpoints\Indexes;
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
            $this->entityManager->persist(new Post());
        }

        $this->entityManager->flush();

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
            $this->entityManager->persist(new Article());
        }

        for ($i = 0; $i <= 5; ++$i) {
            $this->entityManager->persist(new Podcast());
        }

        $this->entityManager->flush();

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
            $this->entityManager->persist(new Page(new DummyObjectId($i)));
        }

        $this->entityManager->flush();

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
            $this->entityManager->persist(new Page(new DummyObjectId($i)));
        }

        $this->entityManager->flush();

        $importCommand = $this->application->find('meilisearch:import');
        $importCommandTester = new CommandTester($importCommand);
        $return = $importCommandTester->execute([
            '--indices' => 'pages',
            '--response-timeout' => 10000,
        ]);
        $output = $importCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Importing for index Meilisearch\Bundle\Tests\Entity\Page
Indexed a batch of 10 / 10 Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index (10 indexed since start)
Done!

EOD, $output);
        $this->assertSame(0, $return);

        // Reset all
        parent::setUp();

        for ($i = 0; $i < 10; ++$i) {
            $this->entityManager->persist(new Page(new DummyObjectId($i)));
        }

        $this->entityManager->flush();

        // test if it will work with a bad option
        $importCommand = $this->application->find('meilisearch:import');
        $importCommandTester = new CommandTester($importCommand);
        $return = $importCommandTester->execute([
            '--indices' => 'pages',
            '--response-timeout' => 'asd',
        ]);
        $output = $importCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Importing for index Meilisearch\Bundle\Tests\Entity\Page
Indexed a batch of 10 / 10 Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index (10 indexed since start)
Done!

EOD, $output);
        $this->assertSame(0, $return);
    }

    /**
     * Importing 'Tag' and 'Link' into the same 'tags' index.
     */
    public function testImportDifferentEntitiesIntoSameIndex(): void
    {
        for ($i = 0; $i <= 5; ++$i) {
            $this->entityManager->persist(new Tag($i));
        }

        $this->entityManager->persist(new Link(60, 'Test Link 60', 'http://link60', true));
        $this->entityManager->persist(new Link(61, 'Test Link 61', 'http://link61', true));

        $this->entityManager->flush();

        $command = $this->application->find('meilisearch:import');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--indices' => 'tags']);

        $this->assertSame(<<<'EOD'
Importing for index Meilisearch\Bundle\Tests\Entity\Tag
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Tag entities into sf_phpunit__tags index (6 indexed since start)
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Tag entities into sf_phpunit__aggregated index (6 indexed since start)
Importing for index Meilisearch\Bundle\Tests\Entity\Link
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Link entities into sf_phpunit__tags index (2 indexed since start)
Done!

EOD, $commandTester->getDisplay());

        $searchResult = $this->client->index($this->getPrefix().'tags')->search('Test');

        $this->assertCount(8, $searchResult->getHits());
        $this->assertSame(8, $searchResult->getHitsCount());
    }

    public function testSearchImportAggregator(): void
    {
        for ($i = 0; $i <= 5; ++$i) {
            $this->entityManager->persist(new Post());
        }

        $this->entityManager->flush();

        $command = $this->application->find('meilisearch:import');
        $commandTester = new CommandTester($command);
        $return = $commandTester->execute(['--indices' => $this->index->getUid()]);

        $this->assertSame(<<<'EOD'
Importing for index Meilisearch\Bundle\Tests\Entity\Post
Setting "stopWords" updated of "sf_phpunit__posts".
Setting "filterableAttributes" updated of "sf_phpunit__posts".
Setting "searchCutoffMs" updated of "sf_phpunit__posts".
Setting "typoTolerance" updated of "sf_phpunit__posts".
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__posts index (6 indexed since start)
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__aggregated index (6 indexed since start)
Done!

EOD, $commandTester->getDisplay());

        $this->assertSame(0, $return);
    }

    public function testSearchImportWithSkipBatches(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $this->entityManager->persist(new Page(new DummyObjectId($i)));
        }

        $this->entityManager->flush();

        $command = $this->application->find('meilisearch:import');
        $commandTester = new CommandTester($command);
        $return = $commandTester->execute([
            '--indices' => 'pages',
            '--batch-size' => '3',
            '--skip-batches' => '2',
        ]);

        $this->assertSame(<<<'EOD'
Importing for index Meilisearch\Bundle\Tests\Entity\Page
Skipping first 2 batches (6 records)
Indexed a batch of 3 / 3 Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index (3 indexed since start)
Indexed a batch of 1 / 1 Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index (4 indexed since start)
Done!

EOD, $commandTester->getDisplay());
        $this->assertSame(0, $return);
    }

    public function testImportingIndexNameWithAndWithoutPrefix(): void
    {
        for ($i = 0; $i <= 5; ++$i) {
            $this->entityManager->persist(new Post());
        }

        $this->entityManager->flush();

        $command = $this->application->find('meilisearch:import');
        $commandTester = new CommandTester($command);
        $return = $commandTester->execute([
            '--indices' => $this->index->getUid(), // This is the already prefixed name
        ]);

        $this->assertSame(<<<'EOD'
Importing for index Meilisearch\Bundle\Tests\Entity\Post
Setting "stopWords" updated of "sf_phpunit__posts".
Setting "filterableAttributes" updated of "sf_phpunit__posts".
Setting "searchCutoffMs" updated of "sf_phpunit__posts".
Setting "typoTolerance" updated of "sf_phpunit__posts".
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__posts index (6 indexed since start)
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__aggregated index (6 indexed since start)
Done!

EOD, $commandTester->getDisplay());
        $this->assertSame(0, $return);

        // Reset database and MS indexes
        parent::setUp();

        for ($i = 0; $i <= 5; ++$i) {
            $this->entityManager->persist(new Post());
        }

        $this->entityManager->flush();

        $command = $this->application->find('meilisearch:import');
        $commandTester = new CommandTester($command);
        $return = $commandTester->execute([
            '--indices' => self::$indexName,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertSame(<<<'EOD'
Importing for index Meilisearch\Bundle\Tests\Entity\Post
Setting "stopWords" updated of "sf_phpunit__posts".
Setting "filterableAttributes" updated of "sf_phpunit__posts".
Setting "searchCutoffMs" updated of "sf_phpunit__posts".
Setting "typoTolerance" updated of "sf_phpunit__posts".
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__posts index (6 indexed since start)
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__aggregated index (6 indexed since start)
Done!

EOD, $commandTester->getDisplay());
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
Setting "filterableAttributes" updated of "sf_phpunit__dynamic_settings".
Setting "searchableAttributes" updated of "sf_phpunit__dynamic_settings".
Setting "stopWords" updated of "sf_phpunit__dynamic_settings".
Setting "synonyms" updated of "sf_phpunit__dynamic_settings".
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\DynamicSettings entities into sf_phpunit__dynamic_settings index (6 indexed since start)
Done!

EOD, $importOutput);

        $settings = $this->get('meilisearch.client')->index('sf_phpunit__dynamic_settings')->getSettings();

        $getSetting = static fn ($value) => $value instanceof \IteratorAggregate ? iterator_to_array($value) : $value;

        $filterableAttributes = $getSetting($settings['filterableAttributes']);
        sort($filterableAttributes);
        $expected = ['publishedAt', 'title'];
        sort($expected);
        self::assertSame($expected, $filterableAttributes);
        self::assertSame(['title'], $getSetting($settings['searchableAttributes']));
        self::assertSame(['a', 'n', 'the'], $getSetting($settings['stopWords']));
        self::assertSame(['fantastic' => ['great'], 'great' => ['fantastic']], $getSetting($settings['synonyms']));
    }

    public function testImportUpdatesSettingsOnce(): void
    {
        for ($i = 0; $i <= 3; ++$i) {
            $this->entityManager->persist(new Post());
        }

        $this->entityManager->flush();

        $importCommand = $this->application->find('meilisearch:import');
        $importCommandTester = new CommandTester($importCommand);
        $importCommandTester->execute(['--indices' => 'posts', '--batch-size' => '2']);

        $importOutput = $importCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Importing for index Meilisearch\Bundle\Tests\Entity\Post
Setting "stopWords" updated of "sf_phpunit__posts".
Setting "filterableAttributes" updated of "sf_phpunit__posts".
Setting "searchCutoffMs" updated of "sf_phpunit__posts".
Setting "typoTolerance" updated of "sf_phpunit__posts".
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__posts index (2 indexed since start)
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__aggregated index (2 indexed since start)
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__posts index (4 indexed since start)
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__aggregated index (4 indexed since start)
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
            $this->entityManager->persist(new Post());
        }

        $this->entityManager->flush();

        $command = $this->application->find('meilisearch:import');
        $commandTester = new CommandTester($command);
        $return = $commandTester->execute([
            '--indices' => 'posts',
            '--swap-indices' => true,
            '--no-update-settings' => true,
        ]);

        $this->assertSame(<<<'EOD'
Importing for index Meilisearch\Bundle\Tests\Entity\Post
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Post entities into _tmp_sf_phpunit__posts index (6 indexed since start)
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Post entities into _tmp_sf_phpunit__aggregated index (6 indexed since start)
Swapping indices...
Indices swapped.
Deleting temporary indices...
Deleted _tmp_sf_phpunit__posts
Done!

EOD, $commandTester->getDisplay());
        $this->assertSame(0, $return);
    }
}
