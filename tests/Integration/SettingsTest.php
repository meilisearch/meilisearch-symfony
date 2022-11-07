<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Test\Integration;

use MeiliSearch\Bundle\Test\BaseKernelTestCase;
use MeiliSearch\Client;
use MeiliSearch\Contracts\Index\TypoTolerance;
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
            'words',
            'typo',
            'proximity',
            'attribute',
            'sort',
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

        $this->assertNotEmpty($settings['filterableAttributes']);
        $this->assertEquals(['publishedAt', 'title'], $settings['filterableAttributes']);

        $this->assertArrayHasKey('typoTolerance', $settings);
        $this->assertInstanceOf(TypoTolerance::class, $settings['typoTolerance']);
        $this->assertTrue($settings['typoTolerance']['enabled']);
        $this->assertEquals(['title'], $settings['typoTolerance']['disableOnAttributes']);
        $this->assertEquals(['york'], $settings['typoTolerance']['disableOnWords']);
        $this->assertEquals(['oneTypo' => 5, 'twoTypos' => 9], $settings['typoTolerance']['minWordSizeForTypos']);
    }
}
