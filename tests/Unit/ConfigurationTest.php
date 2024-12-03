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
     * @param mixed $value
     *
     * @dataProvider dataTestSettingsDynamicCheckerInvalid
     */
    public function testSettingsDynamicCheckerInvalid($value): void
    {
        $this->assertConfigurationIsInvalid([
            'meilisearch' => [
                'indices' => [
                    [
                        'name' => 'items',
                        'class' => 'App\Entity\Post',
                        'settings' => $value,
                    ],
                ],
            ],
        ], 'Settings must be an array.');
    }

    /**
     * @param mixed $value
     *
     * @dataProvider dataTestSettingsDynamicCheckerValid
     */
    public function testSettingsDynamicCheckerValid($value): void
    {
        $this->assertConfigurationIsValid([
            'meilisearch' => [
                'indices' => [
                    [
                        'name' => 'items',
                        'class' => 'App\Entity\Post',
                        'settings' => $value,
                    ],
                ],
            ],
        ]);
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
                'http_client' => 'psr18.http_client',
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
                'http_client' => 'psr18.http_client',
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
                'http_client' => 'psr18.http_client',
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
                'http_client' => 'psr18.http_client',
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
                'http_client' => 'psr18.http_client',
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
                'http_client' => 'psr18.http_client',
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
                'http_client' => 'psr18.http_client',
            ],
        ];

        yield 'custom http client' => [
            'inputConfig' => [
                'meilisearch' => [
                    'http_client' => 'acme.http_client',
                ],
            ],
            'expectedConfig' => [
                'url' => 'http://localhost:7700',
                'prefix' => null,
                'indices' => [],
                'nbResults' => 20,
                'batchSize' => 500,
                'serializer' => 'serializer',
                'doctrineSubscribedEvents' => ['postPersist', 'postUpdate', 'preRemove'],
                'http_client' => 'acme.http_client',
            ],
        ];
    }

    /**
     * @return iterable<array{value: mixed}>
     */
    public static function dataTestSettingsDynamicCheckerInvalid(): iterable
    {
        yield 'string is not acceptable' => [
            'value' => 'hello',
        ];
        yield 'int is not acceptable' => [
            'value' => 1,
        ];
        yield 'bool is not acceptable' => [
            'value' => true,
        ];
    }

    /**
     * @return iterable<array{value: mixed}>
     */
    public static function dataTestSettingsDynamicCheckerValid(): iterable
    {
        yield 'array is acceptable' => [
            'value' => [],
        ];
        yield 'array with arbitrary key is acceptable' => [
            'value' => [
                'key' => 'value',
                'key2' => 'value2',
            ],
        ];
        yield 'null is acceptable' => [
            'value' => null,
        ];
    }

    protected function getConfiguration(): Configuration
    {
        return new Configuration();
    }
}
