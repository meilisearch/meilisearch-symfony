<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Integration;

use Meilisearch\Bundle\Tests\BaseKernelTestCase;
use Meilisearch\Contracts\Index\TypoTolerance;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SettingsTest extends BaseKernelTestCase
{
    private static string $indexName = 'posts';

    public const DEFAULT_RANKING_RULES = [
        'words',
        'typo',
        'proximity',
        'attribute',
        'sort',
        'exactness',
    ];

    protected Application $application;

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

        $this->assertStringContainsString('Setting "stopWords" updated of "sf_phpunit__posts".', $output);
        $this->assertSame(['a', 'an', 'the'], $settings['stopWords']);

        $this->assertStringContainsString('Setting "searchCutoffMs" updated of "sf_phpunit__posts".', $output);
        $this->assertSame(1500, $settings['searchCutoffMs']);

        $this->assertStringContainsString('Setting "filterableAttributes" updated of "sf_phpunit__posts".', $output);
        $this->assertSame(['publishedAt', 'title'], $settings['filterableAttributes']);

        $this->assertStringContainsString('Setting "typoTolerance" updated of "sf_phpunit__posts".', $output);
        $this->assertArrayHasKey('typoTolerance', $settings);
        $this->assertInstanceOf(TypoTolerance::class, $settings['typoTolerance']);
        $this->assertTrue($settings['typoTolerance']['enabled']);
        $this->assertSame(['title'], $settings['typoTolerance']['disableOnAttributes']);
        $this->assertSame(['york'], $settings['typoTolerance']['disableOnWords']);
        $this->assertSame(['oneTypo' => 5, 'twoTypos' => 9], $settings['typoTolerance']['minWordSizeForTypos']);
    }
}
