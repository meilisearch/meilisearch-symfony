<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit;

use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use Meilisearch\Bundle\DependencyInjection\Configuration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ConfigurationTest extends KernelTestCase
{
    use ConfigurationTestCaseTrait;

    /**
     * @param array<mixed> $inputConfig
     * @param array<mixed> $expectedConfig
     *
     * @dataProvider dataTestConfigurationTree
     */
    public function testValidConfig(array $inputConfig, array $expectedConfig): void
    {
        $this->assertProcessedConfigurationEquals($inputConfig, $expectedConfig);
    }

    /**
     * @return iterable<array{inputConfig: array<mixed>, expectedConfig: array<mixed>}>
     */
    public static function dataTestConfigurationTree(): iterable
    {
        yield 'test empty config for default value' => [
            'inputConfig' => [
                'meilisearch' => [],
            ],
            'expectedConfig' => [
                'url' => 'http://localhost:7700',
                'prefix' => null,
                'nbResults' => 20,
                'batchSize' => 500,
                'serializer' => 'serializer',
                'doctrineSubscribedEvents' => ['postPersist', 'postUpdate', 'preRemove'],
                'indices' => [],
            ],
        ];

        yield 'simple config' => [
            'inputConfig' => [
                'meilisearch' => [
                    'url' => 'http://meilisearch:7700',
                    'prefix' => 'sf_',
                    'nbResults' => 40,
                    'batchSize' => 100,
                ],
            ],
            'expectedConfig' => [
                'url' => 'http://meilisearch:7700',
                'prefix' => 'sf_',
                'nbResults' => 40,
                'batchSize' => 100,
                'serializer' => 'serializer',
                'doctrineSubscribedEvents' => ['postPersist', 'postUpdate', 'preRemove'],
                'indices' => [],
            ],
        ];

        yield 'index config' => [
            'inputConfig' => [
                'meilisearch' => [
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
            ],
            'expectedConfig' => [
                'url' => 'http://localhost:7700',
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
                        'serializer_groups' => ['searchable'],
                        'index_if' => null,
                        'settings' => [],
                    ],
                    1 => [
                        'name' => 'tags',
                        'class' => 'App\Entity\Tag',
                        'enable_serializer_groups' => true,
                        'serializer_groups' => ['searchable'],
                        'index_if' => null,
                        'settings' => [],
                    ],
                ],
            ],
        ];

        yield 'same index for multiple models' => [
            'inputConfig' => [
                'meilisearch' => [
                    'prefix' => 'sf_',
                    'indices' => [
                        [
                            'name' => 'items',
                            'class' => 'App\Entity\Post',
                            'enable_serializer_groups' => false,
                            'index_if' => null,
                            'settings' => [],
                        ],
                        [
                            'name' => 'items',
                            'class' => 'App\Entity\Tag',
                            'enable_serializer_groups' => false,
                            'index_if' => null,
                            'settings' => [],
                        ],
                    ],
                    'nbResults' => 20,
                    'batchSize' => 500,
                    'serializer' => 'serializer',
                    'doctrineSubscribedEvents' => ['postPersist', 'postUpdate', 'preRemove'],
                ],
            ],
            'expectedConfig' => [
                'url' => 'http://localhost:7700',
                'prefix' => 'sf_',
                'indices' => [
                    [
                        'name' => 'items',
                        'class' => 'App\Entity\Post',
                        'enable_serializer_groups' => false,
                        'serializer_groups' => ['searchable'],
                        'index_if' => null, 'settings' => [],
                    ],
                    [
                        'name' => 'items',
                        'class' => 'App\Entity\Tag',
                        'enable_serializer_groups' => false,
                        'serializer_groups' => ['searchable'],
                        'index_if' => null,
                        'settings' => [],
                    ],
                ],
                'nbResults' => 20,
                'batchSize' => 500,
                'serializer' => 'serializer',
                'doctrineSubscribedEvents' => ['postPersist', 'postUpdate', 'preRemove'],
            ],
        ];

        yield 'custom serializer groups' => [
            'inputConfig' => [
                'meilisearch' => [
                    'prefix' => 'sf_',
                    'indices' => [
                        [
                            'name' => 'items',
                            'class' => 'App\Entity\Post',
                            'enable_serializer_groups' => true,
                            'serializer_groups' => ['post.public', 'post.private'],
                            'index_if' => null,
                            'settings' => [],
                        ],
                    ],
                ],
            ],
            'expectedConfig' => [
                'url' => 'http://localhost:7700',
                'prefix' => 'sf_',
                'indices' => [
                    [
                        'name' => 'items',
                        'class' => 'App\Entity\Post',
                        'enable_serializer_groups' => true,
                        'serializer_groups' => ['post.public', 'post.private'],
                        'index_if' => null, 'settings' => [],
                    ],
                ],
                'nbResults' => 20,
                'batchSize' => 500,
                'serializer' => 'serializer',
                'doctrineSubscribedEvents' => ['postPersist', 'postUpdate', 'preRemove'],
            ],
        ];

        yield 'distinct attribute' => [
            'inputConfig' => [
                'meilisearch' => [
                    'prefix' => 'sf_',
                    'indices' => [
                        [
                            'name' => 'items',
                            'class' => 'App\Entity\Post',
                            'settings' => [
                                'distinctAttribute' => 'product_id',
                            ],
                        ],
                    ],
                ],
            ],
            'expectedConfig' => [
                'url' => 'http://localhost:7700',
                'prefix' => 'sf_',
                'indices' => [
                    [
                        'name' => 'items',
                        'class' => 'App\Entity\Post',
                        'enable_serializer_groups' => false,
                        'serializer_groups' => ['searchable'],
                        'index_if' => null,
                        'settings' => [
                            'distinctAttribute' => ['product_id'],
                        ],
                    ],
                ],
                'nbResults' => 20,
                'batchSize' => 500,
                'serializer' => 'serializer',
                'doctrineSubscribedEvents' => ['postPersist', 'postUpdate', 'preRemove'],
            ],
        ];

        yield 'proximity precision' => [
            'inputConfig' => [
                'meilisearch' => [
                    'prefix' => 'sf_',
                    'indices' => [
                        [
                            'name' => 'items',
                            'class' => 'App\Entity\Post',
                            'settings' => [
                                'proximityPrecision' => 'byWord',
                            ],
                        ],
                    ],
                ],
            ],
            'expectedConfig' => [
                'url' => 'http://localhost:7700',
                'prefix' => 'sf_',
                'indices' => [
                    [
                        'name' => 'items',
                        'class' => 'App\Entity\Post',
                        'enable_serializer_groups' => false,
                        'serializer_groups' => ['searchable'],
                        'index_if' => null,
                        'settings' => [
                            'proximityPrecision' => ['byWord'],
                        ],
                    ],
                ],
                'nbResults' => 20,
                'batchSize' => 500,
                'serializer' => 'serializer',
                'doctrineSubscribedEvents' => ['postPersist', 'postUpdate', 'preRemove'],
            ],
        ];
    }

    protected function getConfiguration(): Configuration
    {
        return new Configuration();
    }
}
