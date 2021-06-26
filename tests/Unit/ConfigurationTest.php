<?php

declare(strict_types=1);

namespace MeiliSearch\Bundle\Test\Unit;

use MeiliSearch\Bundle\DependencyInjection\Configuration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Class ConfigurationTest.
 */
class ConfigurationTest extends KernelTestCase
{
    /**
     * @dataProvider dataTestConfigurationTree
     *
     * @param mixed $inputConfig
     * @param mixed $expectedConfig
     */
    public function testConfigurationTree($inputConfig, $expectedConfig): void
    {
        $configuration = new Configuration();

        $node = $configuration->getConfigTreeBuilder()->buildTree();
        $normalizedConfig = $node->normalize($inputConfig);
        $finalizedConfig = $node->finalize($normalizedConfig);

        $this->assertEquals($expectedConfig, $finalizedConfig);
    }

    public function dataTestConfigurationTree(): array
    {
        return [
            'test empty config for default value' => [
                [],
                [
                    'prefix' => null,
                    'nbResults' => 20,
                    'batchSize' => 500,
                    'serializer' => 'serializer',
                    'doctrineSubscribedEvents' => ['postPersist', 'postUpdate', 'preRemove'],
                    'indices' => [],
                ],
            ],
            'Simple config' => [
                [
                    'prefix' => 'sf_',
                    'nbResults' => 40,
                    'batchSize' => 100,
                ],
                [
                    'prefix' => 'sf_',
                    'nbResults' => 40,
                    'batchSize' => 100,
                    'serializer' => 'serializer',
                    'doctrineSubscribedEvents' => ['postPersist', 'postUpdate', 'preRemove'],
                    'indices' => [],
                ],
            ],
            'Index config' => [
                [
                    'prefix' => 'sf_',
                    'indices' => [
                        ['name' => 'posts', 'class' => 'App\Entity\Post', 'index_if' => null],
                        [
                            'name' => 'tags',
                            'class' => 'App\Entity\Tag',
                            'enable_serializer_groups' => true,
                            'index_if' => null,
                        ],
                    ],
                ],
                [
                    'prefix' => 'sf_',
                    'nbResults' => 20,
                    'batchSize' => 500,
                    'serializer' => 'serializer',
                    'doctrineSubscribedEvents' => ['postPersist', 'postUpdate', 'preRemove'],
                    'indices' => [
                        0 => [
                            'name' => 'posts',
                            'class' => 'App\Entity\Post',
                            'enable_serializer_groups' => false,
                            'index_if' => null,
                            'settings' => [],
                        ],
                        1 => [
                            'name' => 'tags',
                            'class' => 'App\Entity\Tag',
                            'enable_serializer_groups' => true,
                            'index_if' => null,
                            'settings' => [],
                        ],
                    ],
                ],
            ],
            'same index for multiple models' => [
                [
                    'prefix' => 'sf_',
                    'indices' => [
                        [
                            'name' => 'items', 'class' => 'App\Entity\Post', 'enable_serializer_groups' => false, 'index_if' => null, 'settings' => [],
                        ],
                        [
                            'name' => 'items', 'class' => 'App\Entity\Tag', 'enable_serializer_groups' => false, 'index_if' => null, 'settings' => [],
                        ],
                    ],
                    'nbResults' => 20,
                    'batchSize' => 500,
                    'serializer' => 'serializer',
                    'doctrineSubscribedEvents' => ['postPersist', 'postUpdate', 'preRemove'],
                ],
                [
                    'prefix' => 'sf_',
                    'indices' => [
                        [
                            'name' => 'items', 'class' => 'App\Entity\Post', 'enable_serializer_groups' => false, 'index_if' => null, 'settings' => [],
                        ],
                        [
                            'name' => 'items', 'class' => 'App\Entity\Tag',  'enable_serializer_groups' => false, 'index_if' => null, 'settings' => [],
                        ],
                    ],
                    'nbResults' => 20,
                    'batchSize' => 500,
                    'serializer' => 'serializer',
                    'doctrineSubscribedEvents' => ['postPersist', 'postUpdate', 'preRemove'],
                ],
            ],
        ];
    }
}
