<?php

declare(strict_types=1);

namespace Meilisearch\Bundle\Tests\Unit;

use Meilisearch\Bundle\DependencyInjection\Configuration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Class ConfigurationTest.
 */
class ConfigurationTest extends KernelTestCase
{
    /**
     * @dataProvider dataTestConfigurationTree
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
                    'url' => 'http://localhost:7700',
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
                    'url' => 'http://meilisearch:7700',
                    'prefix' => 'sf_',
                    'nbResults' => 40,
                    'batchSize' => 100,
                ],
                [
                    'url' => 'http://meilisearch:7700',
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
                        [
                            'name' => 'posts',
                            'class' => 'App\Entity\Post',
                            'index_if' => null,
                            'repository_method' => null,
                        ],
                        [
                            'name' => 'tags',
                            'class' => 'App\Entity\Tag',
                            'enable_serializer_groups' => true,
                            'index_if' => null,
                            'repository_method' => null,
                        ],
                    ],
                ],
                [
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
                            'repository_method' => null,
                        ],
                        1 => [
                            'name' => 'tags',
                            'class' => 'App\Entity\Tag',
                            'enable_serializer_groups' => true,
                            'serializer_groups' => ['searchable'],
                            'index_if' => null,
                            'settings' => [],
                            'repository_method' => null,
                        ],
                    ],
                ],
            ],
            'same index for multiple models' => [
                [
                    'prefix' => 'sf_',
                    'indices' => [
                        [
                            'name' => 'items',
                            'class' => 'App\Entity\Post',
                            'enable_serializer_groups' => false,
                            'index_if' => null,
                            'settings' => [],
                            'repository_method' => null,
                        ],
                        [
                            'name' => 'items',
                            'class' => 'App\Entity\Tag',
                            'enable_serializer_groups' => false,
                            'index_if' => null,
                            'settings' => [],
                            'repository_method' => null,
                        ],
                    ],
                    'nbResults' => 20,
                    'batchSize' => 500,
                    'serializer' => 'serializer',
                    'doctrineSubscribedEvents' => ['postPersist', 'postUpdate', 'preRemove'],
                ],
                [
                    'url' => 'http://localhost:7700',
                    'prefix' => 'sf_',
                    'indices' => [
                        [
                            'name' => 'items',
                            'class' => 'App\Entity\Post',
                            'enable_serializer_groups' => false,
                            'serializer_groups' => ['searchable'],
                            'index_if' => null,
                            'settings' => [],
                            'repository_method' => null,
                        ],
                        [
                            'name' => 'items',
                            'class' => 'App\Entity\Tag',
                            'enable_serializer_groups' => false,
                            'serializer_groups' => ['searchable'],
                            'index_if' => null,
                            'settings' => [],
                            'repository_method' => null,
                        ],
                    ],
                    'nbResults' => 20,
                    'batchSize' => 500,
                    'serializer' => 'serializer',
                    'doctrineSubscribedEvents' => ['postPersist', 'postUpdate', 'preRemove'],
                ],
            ],
            'Custom serializer groups' => [
                [
                    'prefix' => 'sf_',
                    'indices' => [
                        [
                            'name' => 'items',
                            'class' => 'App\Entity\Post',
                            'enable_serializer_groups' => true,
                            'serializer_groups' => ['post.public', 'post.private'],
                            'index_if' => null,
                            'settings' => [],
                            'repository_method' => null,
                        ],
                    ],
                ],
                [
                    'url' => 'http://localhost:7700',
                    'prefix' => 'sf_',
                    'indices' => [
                        [
                            'name' => 'items',
                            'class' => 'App\Entity\Post',
                            'enable_serializer_groups' => true,
                            'serializer_groups' => ['post.public', 'post.private'],
                            'index_if' => null,
                            'settings' => [],
                            'repository_method' => null,
                        ],
                    ],
                    'nbResults' => 20,
                    'batchSize' => 500,
                    'serializer' => 'serializer',
                    'doctrineSubscribedEvents' => ['postPersist', 'postUpdate', 'preRemove'],
                ],
            ],
            'distinct attribute' => [
                [
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
                [
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
                            'repository_method' => null,
                        ],
                    ],
                    'nbResults' => 20,
                    'batchSize' => 500,
                    'serializer' => 'serializer',
                    'doctrineSubscribedEvents' => ['postPersist', 'postUpdate', 'preRemove'],
                ],
            ],
            'proximity precision' => [
                [
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
                [
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
                            'repository_method' => null,
                        ],
                    ],
                    'nbResults' => 20,
                    'batchSize' => 500,
                    'serializer' => 'serializer',
                    'doctrineSubscribedEvents' => ['postPersist', 'postUpdate', 'preRemove'],
                ],
            ],
            'custom repository method' => [
                [
                    'prefix' => 'sf_',
                    'indices' => [
                        [
                            'name' => 'items',
                            'class' => 'App\Entity\Post',
                            'repository_method' => 'myCustomRepositoryMethod',
                        ],
                    ],
                ],
                [
                    'url' => 'http://localhost:7700',
                    'prefix' => 'sf_',
                    'indices' => [
                        [
                            'name' => 'items',
                            'class' => 'App\Entity\Post',
                            'enable_serializer_groups' => false,
                            'serializer_groups' => ['searchable'],
                            'index_if' => null,
                            'settings' => [],
                            'repository_method' => 'myCustomRepositoryMethod',
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
