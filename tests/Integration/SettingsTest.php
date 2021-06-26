<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Test\Integration;

use MeiliSearch\Bundle\Test\BaseKernelTestCase;
use MeiliSearch\Client;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class SettingsTest.
 */
class SettingsTest extends BaseKernelTestCase
{
    private static string $indexName = 'posts';

    public const DEFAULT_RANKING_RULES
        = [
            'typo',
            'words',
            'proximity',
            'attribute',
            'wordsPosition',
            'exactness',
        ];

    protected Client $client;
    protected Application $application;

    /**
     * @throws \Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->client = $this->get('search.client');
        $this->application = new Application(self::$kernel);
    }

    public function testGetDefaultSettings(): void
    {
        $primaryKey = 'ObjectID';
        $settingA = $this->client->getOrCreateIndex($this->getPrefix().'indexA')->getSettings();
        $settingB = $this->client->getOrCreateIndex($this->getPrefix().'indexB', ['primaryKey' => $primaryKey])->getSettings();

        $this->assertEquals(self::DEFAULT_RANKING_RULES, $settingA['rankingRules']);
        $this->assertNull($settingA['distinctAttribute']);
        $this->assertIsArray($settingA['searchableAttributes']);
        $this->assertEquals(['*'], $settingA['searchableAttributes']);
        $this->assertIsArray($settingA['displayedAttributes']);
        $this->assertEquals(['*'], $settingA['displayedAttributes']);
        $this->assertIsArray($settingA['stopWords']);
        $this->assertEmpty($settingA['stopWords']);
        $this->assertIsArray($settingA['synonyms']);
        $this->assertEmpty($settingA['synonyms']);

        $this->assertEquals(self::DEFAULT_RANKING_RULES, $settingB['rankingRules']);
        $this->assertNull($settingB['distinctAttribute']);
        $this->assertEquals(['*'], $settingB['searchableAttributes']);
        $this->assertEquals(['*'], $settingB['displayedAttributes']);
        $this->assertIsArray($settingB['stopWords']);
        $this->assertEmpty($settingB['stopWords']);
        $this->assertIsArray($settingB['synonyms']);
        $this->assertEmpty($settingB['synonyms']);
    }

    public function testUpdateSettings(): void
    {
        $index = $this->getPrefix().self::$indexName;

        $command = $this->application->find('meili:import');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--indices' => $index,
            '--update-settings' => true,
        ]);

        $settings = $this->client->index($index)->getSettings();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Settings updated.', $output);
        $this->assertNotEmpty($settings['stopWords']);
        $this->assertEquals(['a', 'an', 'the'], $settings['stopWords']);

        $this->assertNotEmpty($settings['attributesForFaceting']);
        $this->assertEquals(['title', 'publishedAt'], $settings['attributesForFaceting']);
    }
}
