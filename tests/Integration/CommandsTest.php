<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration;

use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Bundle\Tests\Entity\DummyCustomGroups;
use Meilisearch\Bundle\Tests\Entity\SelfNormalizable;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Search\SearchResult;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class CommandsTest.
 */
class CommandsTest extends BaseKernelTestCase
{
    private static string $indexName = 'posts';

    protected Client $client;
    protected Application $application;
    protected Indexes $index;

    /**
     * @throws ApiException
     * @throws \Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->get('search.client');
        $this->index = $this->client->index($this->getPrefix().self::$indexName);
        $this->application = new Application(self::createKernel());
    }

    public function testSearchClearUnknownIndex(): void
    {
        $unknownIndexName = 'test';

        $command = $this->application->find('meili:clear');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--indices' => $unknownIndexName,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Cannot clear index. Not found.', $output);
    }

    public function testSearchImportAndClearAndDeleteWithoutIndices(): void
    {
        for ($i = 0; $i <= 5; ++$i) {
            $this->createPost();
        }

        for ($i = 0; $i <= 5; ++$i) {
            $this->createPage($i);
        }

        for ($i = 0; $i <= 5; ++$i) {
            $this->createTag(['id' => $i]);
        }

        $importCommand = $this->application->find('meili:import');
        $importCommandTester = new CommandTester($importCommand);
        $importCommandTester->execute([]);

        $importOutput = $importCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Importing for index Meilisearch\Bundle\Tests\Entity\Post
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__posts index (6 indexed since start)
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__aggregated index (6 indexed since start)
Settings updated.
Settings updated.
Settings updated.
Importing for index Meilisearch\Bundle\Tests\Entity\Comment
Importing for index Meilisearch\Bundle\Tests\Entity\Tag
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Tag entities into sf_phpunit__tags index (6 indexed since start)
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Tag entities into sf_phpunit__aggregated index (6 indexed since start)
Importing for index Meilisearch\Bundle\Tests\Entity\Link
Importing for index Meilisearch\Bundle\Tests\Entity\Page
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index (6 indexed since start)
Importing for index Meilisearch\Bundle\Tests\Entity\SelfNormalizable
Importing for index Meilisearch\Bundle\Tests\Entity\DummyCustomGroups
Importing for index Meilisearch\Bundle\Tests\Entity\Post
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__posts index (6 indexed since start)
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Post entities into sf_phpunit__aggregated index (6 indexed since start)
Importing for index Meilisearch\Bundle\Tests\Entity\Tag
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Tag entities into sf_phpunit__tags index (6 indexed since start)
Indexed a batch of 6 / 6 Meilisearch\Bundle\Tests\Entity\Tag entities into sf_phpunit__aggregated index (6 indexed since start)
Done!

EOD, $importOutput);

        $clearCommand = $this->application->find('meili:clear');
        $clearCommandTester = new CommandTester($clearCommand);
        $clearCommandTester->execute([]);

        $clearOutput = $clearCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Cleared sf_phpunit__posts index of Meilisearch\Bundle\Tests\Entity\Post
Cleared sf_phpunit__comments index of Meilisearch\Bundle\Tests\Entity\Comment
Cleared sf_phpunit__aggregated index of Meilisearch\Bundle\Tests\Entity\ContentAggregator
Cleared sf_phpunit__tags index of Meilisearch\Bundle\Tests\Entity\Tag
Cleared sf_phpunit__tags index of Meilisearch\Bundle\Tests\Entity\Link
Cleared sf_phpunit__pages index of Meilisearch\Bundle\Tests\Entity\Page
Cleared sf_phpunit__self_normalizable index of Meilisearch\Bundle\Tests\Entity\SelfNormalizable
Cleared sf_phpunit__dummy_custom_groups index of Meilisearch\Bundle\Tests\Entity\DummyCustomGroups
Done!

EOD, $clearOutput);

        $clearCommand = $this->application->find('meili:delete');
        $clearCommandTester = new CommandTester($clearCommand);
        $clearCommandTester->execute([]);

        $clearOutput = $clearCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Deleted sf_phpunit__posts
Deleted sf_phpunit__comments
Deleted sf_phpunit__aggregated
Deleted sf_phpunit__tags
Deleted sf_phpunit__pages
Deleted sf_phpunit__self_normalizable
Deleted sf_phpunit__dummy_custom_groups
Done!

EOD, $clearOutput);
    }

    public function testSearchImportWithCustomBatchSize(): void
    {
        for ($i = 0; $i <= 10; ++$i) {
            $this->createPage($i);
        }

        $importCommand = $this->application->find('meili:import');
        $importCommandTester = new CommandTester($importCommand);
        $importCommandTester->execute([
            '--indices' => 'pages',
            '--batch-size' => '2',
        ]);

        $importOutput = $importCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Importing for index Meilisearch\Bundle\Tests\Entity\Page
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index (2 indexed since start)
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index (4 indexed since start)
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index (6 indexed since start)
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index (8 indexed since start)
Indexed a batch of 2 / 2 Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index (10 indexed since start)
Indexed a batch of 1 / 1 Meilisearch\Bundle\Tests\Entity\Page entities into sf_phpunit__pages index (11 indexed since start)
Done!

EOD, $importOutput);
    }

    public function testSearchImportWithCustomResponseTimeout(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $this->createPage($i);
        }

        $importCommand = $this->application->find('meili:import');
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
        $importCommand = $this->application->find('meili:import');
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

        $command = $this->application->find('meili:import');
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

        $command = $this->application->find('meili:import');
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

        $command = $this->application->find('meili:import');
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

        $command = $this->application->find('meili:import');
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

        $command = $this->application->find('meili:import');
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

    public function testSearchCreateWithoutIndices(): void
    {
        $createCommand = $this->application->find('meili:create');
        $createCommandTester = new CommandTester($createCommand);
        $createCommandTester->execute([]);

        $createOutput = $createCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Creating index sf_phpunit__posts for Meilisearch\Bundle\Tests\Entity\Post
Creating index sf_phpunit__comments for Meilisearch\Bundle\Tests\Entity\Comment
Creating index sf_phpunit__tags for Meilisearch\Bundle\Tests\Entity\Tag
Creating index sf_phpunit__tags for Meilisearch\Bundle\Tests\Entity\Link
Creating index sf_phpunit__pages for Meilisearch\Bundle\Tests\Entity\Page
Creating index sf_phpunit__self_normalizable for Meilisearch\Bundle\Tests\Entity\SelfNormalizable
Creating index sf_phpunit__dummy_custom_groups for Meilisearch\Bundle\Tests\Entity\DummyCustomGroups
Creating index sf_phpunit__aggregated for Meilisearch\Bundle\Tests\Entity\Post
Creating index sf_phpunit__aggregated for Meilisearch\Bundle\Tests\Entity\Tag
Done!

EOD, $createOutput);
    }

    public function testSearchCreateWithIndices(): void
    {
        $createCommand = $this->application->find('meili:create');
        $createCommandTester = new CommandTester($createCommand);
        $createCommandTester->execute([
            '--indices' => 'posts',
        ]);

        $createOutput = $createCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Creating index sf_phpunit__posts for Meilisearch\Bundle\Tests\Entity\Post
Done!

EOD, $createOutput);
    }

    public function testCreateExecuteIndexCreation(): void
    {
        $createCommand = $this->application->find('meili:create');
        $createCommandTester = new CommandTester($createCommand);
        $createCommandTester->execute([]);

        $this->assertEquals($this->client->getTasks()->getResults()[0]['type'], 'indexCreation');
    }

    public function testImportsSelfNormalizable(): void
    {
        for ($i = 1; $i <= 2; ++$i) {
            $this->entityManager->persist(new SelfNormalizable($i, "Self normalizabie $i"));
        }

        $this->entityManager->flush();

        $importCommand = $this->application->find('meili:import');
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

        $importCommand = $this->application->find('meili:import');
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
                'createdAt' => '2024-04-04T07:32:01+00:00',
            ],
            [
                'objectID' => 2,
                'id' => 2,
                'name' => 'Dummy 2',
                'createdAt' => '2024-04-04T07:32:02+00:00',
            ],
        ], $this->client->index('sf_phpunit__dummy_custom_groups')->getDocuments()->getResults());
    }
}
