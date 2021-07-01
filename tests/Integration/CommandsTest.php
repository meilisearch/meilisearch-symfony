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
        $this->index = $this->client->getOrCreateIndex($this->getPrefix().self::$indexName);
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
        $searchResult = $this->client->getOrCreateIndex($this->getPrefix().'tags')->search('Test');
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
}
