<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Test\Integration;

use MeiliSearch\Bundle\Test\BaseKernelTestCase;
use MeiliSearch\Client;
use MeiliSearch\Endpoints\Indexes;
use MeiliSearch\Exceptions\ApiException;
use MeiliSearch\Search\SearchResult;
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
Importing for index MeiliSearch\Bundle\Test\Entity\Post
Indexed 6 / 6 MeiliSearch\Bundle\Test\Entity\Post entities into sf_phpunit__posts index
Indexed 6 / 6 MeiliSearch\Bundle\Test\Entity\Post entities into sf_phpunit__aggregated index
Settings updated.
Settings updated.
Importing for index MeiliSearch\Bundle\Test\Entity\Comment
Importing for index MeiliSearch\Bundle\Test\Entity\Tag
Indexed 6 / 6 MeiliSearch\Bundle\Test\Entity\Tag entities into sf_phpunit__tags index
Indexed 6 / 6 MeiliSearch\Bundle\Test\Entity\Tag entities into sf_phpunit__aggregated index
Importing for index MeiliSearch\Bundle\Test\Entity\Link
Importing for index MeiliSearch\Bundle\Test\Entity\Page
Indexed 6 / 6 MeiliSearch\Bundle\Test\Entity\Page entities into sf_phpunit__pages index
Importing for index MeiliSearch\Bundle\Test\Entity\Post
Indexed 6 / 6 MeiliSearch\Bundle\Test\Entity\Post entities into sf_phpunit__posts index
Indexed 6 / 6 MeiliSearch\Bundle\Test\Entity\Post entities into sf_phpunit__aggregated index
Importing for index MeiliSearch\Bundle\Test\Entity\Tag
Indexed 6 / 6 MeiliSearch\Bundle\Test\Entity\Tag entities into sf_phpunit__tags index
Indexed 6 / 6 MeiliSearch\Bundle\Test\Entity\Tag entities into sf_phpunit__aggregated index
Done!

EOD, $importOutput);

        $clearCommand = $this->application->find('meili:clear');
        $clearCommandTester = new CommandTester($clearCommand);
        $clearCommandTester->execute([]);

        $clearOutput = $clearCommandTester->getDisplay();

        $this->assertSame(<<<'EOD'
Cleared sf_phpunit__posts index of MeiliSearch\Bundle\Test\Entity\Post
Cleared sf_phpunit__comments index of MeiliSearch\Bundle\Test\Entity\Comment
Cleared sf_phpunit__aggregated index of MeiliSearch\Bundle\Test\Entity\ContentAggregator
Cleared sf_phpunit__tags index of MeiliSearch\Bundle\Test\Entity\Tag
Cleared sf_phpunit__tags index of MeiliSearch\Bundle\Test\Entity\Link
Cleared sf_phpunit__pages index of MeiliSearch\Bundle\Test\Entity\Page
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
Importing for index MeiliSearch\Bundle\Test\Entity\Page
Indexed 2 / 2 MeiliSearch\Bundle\Test\Entity\Page entities into sf_phpunit__pages index
Indexed 2 / 2 MeiliSearch\Bundle\Test\Entity\Page entities into sf_phpunit__pages index
Indexed 2 / 2 MeiliSearch\Bundle\Test\Entity\Page entities into sf_phpunit__pages index
Indexed 2 / 2 MeiliSearch\Bundle\Test\Entity\Page entities into sf_phpunit__pages index
Indexed 2 / 2 MeiliSearch\Bundle\Test\Entity\Page entities into sf_phpunit__pages index
Indexed 1 / 1 MeiliSearch\Bundle\Test\Entity\Page entities into sf_phpunit__pages index
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

        $this->assertStringContainsString('Importing for index MeiliSearch\Bundle\Test\Entity\Page', $output);
        $this->assertStringContainsString('Indexed '.$i.' / '.$i.' MeiliSearch\Bundle\Test\Entity\Page entities into sf_phpunit__pages index', $output);
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

        $this->assertStringContainsString('Importing for index MeiliSearch\Bundle\Test\Entity\Page', $output);
        $this->assertStringContainsString('Indexed '.$i.' / '.$i.' MeiliSearch\Bundle\Test\Entity\Page entities into sf_phpunit__pages index', $output);
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
        $this->assertStringContainsString('Importing for index MeiliSearch\Bundle\Test\Entity\Tag', $output);
        $this->assertStringContainsString('Indexed '.$i.' / '.$i.' MeiliSearch\Bundle\Test\Entity\Tag entities into sf_phpunit__tags index', $output);
        $this->assertStringContainsString('Indexed 2 / 2 MeiliSearch\Bundle\Test\Entity\Link entities into sf_phpunit__tags index', $output);
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
        $this->assertStringContainsString('Importing for index MeiliSearch\Bundle\Test\Entity\Post', $output);
        $this->assertStringContainsString('Indexed '.$i.' / '.$i.' MeiliSearch\Bundle\Test\Entity\Post entities into sf_phpunit__'.self::$indexName.' index', $output);
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
        $this->assertStringContainsString('Importing for index MeiliSearch\Bundle\Test\Entity\Post', $output);
        $this->assertStringContainsString('Indexed '.$i.' / '.$i.' MeiliSearch\Bundle\Test\Entity\Post entities into sf_phpunit__'.self::$indexName.' index', $output);
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
        $this->assertStringContainsString('Importing for index MeiliSearch\Bundle\Test\Entity\Post', $output);
        $this->assertStringContainsString('Indexed '.$i.' / '.$i.' MeiliSearch\Bundle\Test\Entity\Post entities into sf_phpunit__'.self::$indexName.' index', $output);
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
Creating index sf_phpunit__posts for MeiliSearch\Bundle\Test\Entity\Post
Creating index sf_phpunit__comments for MeiliSearch\Bundle\Test\Entity\Comment
Creating index sf_phpunit__tags for MeiliSearch\Bundle\Test\Entity\Tag
Creating index sf_phpunit__tags for MeiliSearch\Bundle\Test\Entity\Link
Creating index sf_phpunit__pages for MeiliSearch\Bundle\Test\Entity\Page
Creating index sf_phpunit__aggregated for MeiliSearch\Bundle\Test\Entity\Post
Creating index sf_phpunit__aggregated for MeiliSearch\Bundle\Test\Entity\Tag
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
Creating index sf_phpunit__posts for MeiliSearch\Bundle\Test\Entity\Post
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
}
