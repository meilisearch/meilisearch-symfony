<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration;

use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Contracts\Index\TypoTolerance;
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

    protected Application $application;

    /**
     * @throws \Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->application = new Application(self::$kernel);
    }

    public function testUpdateSettings(): void
    {
        $index = $this->getPrefix().self::$indexName;

        $command = $this->application->find('meilisearch:import');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--indices' => $index,
            '--update-settings' => true,
        ]);

        $settings = $this->client->index($index)->getSettings();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Settings updated of "sf_phpunit__posts".', $output);
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
