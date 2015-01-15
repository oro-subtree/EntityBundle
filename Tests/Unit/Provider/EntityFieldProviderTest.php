<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\Provider;

use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\Translation\Translator;

use Oro\Bundle\EntityBundle\ORM\EntityClassResolver;
use Oro\Bundle\EntityBundle\Provider\EntityFieldProvider;
use Oro\Bundle\EntityBundle\Provider\EntityProvider;
use Oro\Bundle\EntityBundle\Provider\ExclusionProviderInterface;
use Oro\Bundle\EntityBundle\Provider\VirtualFieldProviderInterface;
use Oro\Bundle\EntityBundle\Provider\VirtualRelationProviderInterface;
use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityExtendBundle\Extend\FieldTypeHelper;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
class EntityFieldProviderTest extends \PHPUnit_Framework_TestCase
{
    /** @var \PHPUnit_Framework_MockObject_MockObject|ConfigProvider */
    private $entityConfigProvider;

    /** @var \PHPUnit_Framework_MockObject_MockObject|ConfigProvider */
    private $extendConfigProvider;

    /** @var \PHPUnit_Framework_MockObject_MockObject|EntityClassResolver */
    private $entityClassResolver;

    /** @var \PHPUnit_Framework_MockObject_MockObject|VirtualFieldProviderInterface */
    private $virtualFieldProvider;

    /** @var \PHPUnit_Framework_MockObject_MockObject|VirtualRelationProviderInterface */
    private $virtualRelationProvider;

    /** @var \PHPUnit_Framework_MockObject_MockObject|ManagerRegistry */
    private $doctrine;

    /** @var EntityFieldProvider */
    private $provider;

    /** @var ExclusionProviderInterface */
    private $exclusionProvider;

    protected function setUp()
    {
        $this->entityConfigProvider = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider')
            ->disableOriginalConstructor()
            ->getMock();
        $this->extendConfigProvider = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider')
            ->disableOriginalConstructor()
            ->getMock();
        $this->entityClassResolver = $this->getMockBuilder('Oro\Bundle\EntityBundle\ORM\EntityClassResolver')
            ->disableOriginalConstructor()
            ->getMock();
        $this->entityClassResolver->expects($this->any())
            ->method('getEntityClass')
            ->will(
                $this->returnCallback(
                    function ($entityName) {
                        return str_replace(':', '\\Entity\\', $entityName);
                    }
                )
            );

        /* @var \PHPUnit_Framework_MockObject_MockObject|Translator $translator */
        $translator = $this->getMockBuilder('Symfony\Component\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();
        $translator->expects($this->any())
            ->method('trans')
            ->will($this->returnArgument(0));

        $this->exclusionProvider = $this->getMock('Oro\Bundle\EntityBundle\Provider\ExclusionProviderInterface');

        $entityProvider = new EntityProvider(
            $this->entityConfigProvider,
            $this->extendConfigProvider,
            $this->entityClassResolver,
            $translator
        );
        $entityProvider->setExclusionProvider($this->exclusionProvider);

        $this->doctrine = $this->getMockBuilder('Symfony\Bridge\Doctrine\ManagerRegistry')
            ->disableOriginalConstructor()
            ->getMock();

        $this->virtualFieldProvider = $this->getMock('Oro\Bundle\EntityBundle\Provider\VirtualFieldProviderInterface');

        $this->virtualRelationProvider =
            $this->getMock('Oro\Bundle\EntityBundle\Provider\VirtualRelationProviderInterface');

        $this->provider = new EntityFieldProvider(
            $this->entityConfigProvider,
            $this->extendConfigProvider,
            $this->entityClassResolver,
            new FieldTypeHelper([]),
            $this->doctrine,
            $translator,
            []
        );
        $this->provider->setEntityProvider($entityProvider);
        $this->provider->setVirtualFieldProvider($this->virtualFieldProvider);
        $this->provider->setVirtualRelationProvider($this->virtualRelationProvider);
        $this->provider->setExclusionProvider($this->exclusionProvider);
    }

    public function testGetFieldsNoEntityConfig()
    {
        $entityName = 'Acme:Test';
        $entityClassName = 'Acme\Entity\Test';

        $em = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->doctrine->expects($this->any())
            ->method('getManagerForClass')
            ->with($entityClassName)
            ->will($this->returnValue($em));

        $this->entityConfigProvider->expects($this->any())
            ->method('hasConfig')
            ->with($entityClassName)
            ->will($this->returnValue(false));

        $result = $this->provider->getFields($entityName);
        $this->assertEquals([], $result);
    }

    public function testGetFieldsWithDefaultParameters()
    {
        $config = [
            'Acme\Entity\Test' => [
                'config' => [
                    'label' => 'Test Label',
                    'plural_label' => 'Test Plural Label',
                    'icon' => 'icon-test',
                ],
                'fields' => [
                    'field1' => [
                        'type' => 'integer',
                        'identifier' => true,
                        'config' => [
                            'label' => 'C',
                        ]
                    ],
                    'field2' => [
                        'type' => 'string',
                        'config' => [
                            'label' => 'B',
                        ]
                    ],
                    'field3' => [
                        'type' => 'string',
                        'config' => [
                            'label' => 'A',
                        ]
                    ],
                    'field4' => [
                        'type' => 'string',
                        'config' => []
                    ],
                ]
            ]
        ];
        $this->prepare($config);

        $result = $this->provider->getFields('Acme:Test');
        $expected = [
            [
                'name' => 'field3',
                'type' => 'string',
                'label' => 'A',
            ],
            [
                'name' => 'field4',
                'type' => 'string',
                'label' => 'acme.entity.test.field4.label',
            ],
            [
                'name' => 'field2',
                'type' => 'string',
                'label' => 'B',
            ],
            [
                'name' => 'field1',
                'type' => 'integer',
                'label' => 'C',
                'identifier' => true
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetFieldsWithRelations()
    {
        $this->prepareWithRelations();
        $result = $this->provider->getFields('Acme:Test', true);
        $expected = [
            [
                'name' => 'field3',
                'type' => 'string',
                'label' => 'A',
            ],
            [
                'name' => 'field4',
                'type' => 'string',
                'label' => 'acme.entity.test.field4.label',
            ],
            [
                'name' => 'field2',
                'type' => 'string',
                'label' => 'B',
            ],
            [
                'name' => 'field1',
                'type' => 'integer',
                'label' => 'C',
                'identifier' => true
            ],
            [
                'name' => 'rel1',
                'type' => 'ref-many',
                'label' => 'Rel1',
                'relation_type' => 'ref-many',
                'related_entity_name' => 'Acme\Entity\Test1'
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetFieldsWithRelationsAndDeepLevel()
    {
        $this->prepareWithRelations();
        $result = $this->provider->getFields('Acme:Test', true, false, false, false, 1);
        $expected = [
            [
                'name' => 'field3',
                'type' => 'string',
                'label' => 'A',
            ],
            [
                'name' => 'field4',
                'type' => 'string',
                'label' => 'acme.entity.test.field4.label',
            ],
            [
                'name' => 'field2',
                'type' => 'string',
                'label' => 'B',
            ],
            [
                'name' => 'field1',
                'type' => 'integer',
                'label' => 'C',
                'identifier' => true
            ],
            [
                'name' => 'rel1',
                'type' => 'ref-many',
                'label' => 'Rel1',
                'relation_type' => 'ref-many',
                'related_entity_name' => 'Acme\Entity\Test1',
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetFieldsWithRelationsAndDeepLevelAndEntityDetails()
    {
        $this->prepareWithRelations();
        $result = $this->provider->getFields('Acme:Test', true, false, true, false, 1);
        $expected = [
            [
                'name' => 'field3',
                'type' => 'string',
                'label' => 'A',
            ],
            [
                'name' => 'field4',
                'type' => 'string',
                'label' => 'acme.entity.test.field4.label',
            ],
            [
                'name' => 'field2',
                'type' => 'string',
                'label' => 'B',
            ],
            [
                'name' => 'field1',
                'type' => 'integer',
                'label' => 'C',
                'identifier' => true
            ],
            [
                'name' => 'rel1',
                'type' => 'ref-many',
                'label' => 'Rel1',
                'relation_type' => 'ref-many',
                'related_entity_name' => 'Acme\Entity\Test1',
                'related_entity_label' => 'Test1 Label',
                'related_entity_plural_label' => 'Test1 Plural Label',
                'related_entity_icon' => 'icon-test1',
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetFieldsWithRelationsAndDeepLevelAndLastLevelRelations()
    {
        $this->prepareWithRelations();
        $result = $this->provider->getFields('Acme:Test', true, false, false, false, 1, true);
        $expected = [
            [
                'name' => 'field3',
                'type' => 'string',
                'label' => 'A',
            ],
            [
                'name' => 'field4',
                'type' => 'string',
                'label' => 'acme.entity.test.field4.label',
            ],
            [
                'name' => 'field2',
                'type' => 'string',
                'label' => 'B',
            ],
            [
                'name' => 'field1',
                'type' => 'integer',
                'label' => 'C',
                'identifier' => true
            ],
            [
                'name' => 'rel1',
                'type' => 'ref-many',
                'label' => 'Rel1',
                'relation_type' => 'ref-many',
                'related_entity_name' => 'Acme\Entity\Test1',
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetFieldsWithRelationsAndDeepLevelAndLastLevelRelationsAndEntityDetails()
    {
        $this->prepareWithRelations();
        $result = $this->provider->getFields('Acme:Test', true, false, true, false, 1, true);
        $expected = [
            [
                'name' => 'field3',
                'type' => 'string',
                'label' => 'A',
            ],
            [
                'name' => 'field4',
                'type' => 'string',
                'label' => 'acme.entity.test.field4.label',
            ],
            [
                'name' => 'field2',
                'type' => 'string',
                'label' => 'B',
            ],
            [
                'name' => 'field1',
                'type' => 'integer',
                'label' => 'C',
                'identifier' => true
            ],
            [
                'name' => 'rel1',
                'type' => 'ref-many',
                'label' => 'Rel1',
                'relation_type' => 'ref-many',
                'related_entity_name' => 'Acme\Entity\Test1',
                'related_entity_label' => 'Test1 Label',
                'related_entity_plural_label' => 'Test1 Plural Label',
                'related_entity_icon' => 'icon-test1',
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetFieldsWithRelationsAndDeepLevelAndWithUnidirectional()
    {
        $this->prepareWithRelations();

        $this->entityConfigProvider->expects($this->any())
            ->method('getIds')
            ->will($this->returnValue([new EntityConfigId('entity', 'Acme\\Entity\\Test22')]));

        $result = $this->provider->getFields('Acme:Test1', true, false, false, true, false);
        $expected = [
            [
                'name' => 'Test1field2',
                'type' => 'string',
                'label' => 'A'
            ],
            [
                'name' => 'id',
                'type' => 'integer',
                'label' => 'B',
                'identifier' => true
            ],
            [
                'name' => 'rel1',
                'type' => 'ref-one',
                'label' => 'Rel11',
                'relation_type' => 'ref-one',
                'related_entity_name' => 'Acme\Entity\Test11',
            ],
            [
                'name' => 'Acme\Entity\Test22::uni_rel1',
                'type' => 'ref-one',
                'label' => 'UniRel1 (Test22 Plural Label)',
                'relation_type' => 'ref-one',
                'related_entity_name' => 'Acme\Entity\Test22',
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testGetFieldsWithVirtualRelationsAndEnums()
    {
        $className = 'Acme\Entity\Test';

        $config = [
            $className => [
                'config' => [
                    'label' => 'Test Label',
                    'plural_label' => 'Test Plural Label',
                    'icon' => 'icon-test',
                ],
                'fields' => [
                    'field1' => [
                        'type' => 'integer',
                        'identifier' => true,
                        'config' => [
                            'label' => 'Field 1',
                        ]
                    ],
                ],
                'relations' => [
                    'rel1' => [
                        'target_class' => 'Acme\EnumValue1',
                        'type' => 'ref-one',
                        'config' => [
                            'label' => 'Enum Field',
                        ]
                    ],
                    'rel2' => [
                        'target_class' => 'Acme\EnumValue2',
                        'type' => 'ref-many',
                        'config' => [
                            'label' => 'Multi Enum Field',
                        ]
                    ],
                ]
            ]
        ];
        $this->prepare($config);

        $this->virtualFieldProvider->expects($this->once())
            ->method('getVirtualFields')
            ->with($className)
            ->will($this->returnValue(['rel1', 'rel2']));

        $this->virtualRelationProvider->expects($this->once())
            ->method('getVirtualRelations')
            ->with($className)
            ->will(
                $this->returnValue(
                    [
                        'virtual_relation' => [
                            'relation_type' => 'oneToMany',
                            'related_entity_name' => 'OtherEntity',
                            'query' => [
                                'select' => ['select expression'],
                                'join' => ['join expression']
                            ]
                        ]
                    ]
                )
            );
        $this->virtualFieldProvider->expects($this->exactly(2))
            ->method('getVirtualFieldQuery')
            ->will(
                $this->returnValueMap(
                    [
                        [
                            $className,
                            'rel1',
                            [
                                'select' => [
                                    'return_type' => 'enum',
                                    'filter_by_id' => true
                                ]
                            ]
                        ],
                        [
                            $className,
                            'rel2',
                            [
                                'select' => [
                                    'return_type' => 'multiEnum',
                                    'filter_by_id' => true
                                ]
                            ]
                        ],
                    ]
                )
            );

        $result = $this->provider->getFields('Acme:Test', true, true);
        $expected = [
            [
                'name' => 'rel1',
                'type' => 'enum',
                'label' => 'Enum Field',
                'related_entity_name' => 'Acme\EnumValue1'
            ],
            [
                'name' => 'field1',
                'type' => 'integer',
                'label' => 'Field 1',
                'identifier' => true
            ],
            [
                'name' => 'rel2',
                'type' => 'multiEnum',
                'label' => 'Multi Enum Field',
                'related_entity_name' => 'Acme\EnumValue2'
            ],
            [
                'name' => 'virtual_relation',
                'type' => 'oneToMany',
                'label' => 'acme.entity.test.virtual_relation.label',
                'relation_type' => 'oneToMany',
                'related_entity_name' => 'OtherEntity'
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     *
     * @param array $config
     */
    protected function prepare($config)
    {
        $metadata = [];
        $fieldConfigs = [];
        foreach ($config as $entityClassName => $entityData) {
            $entityMetadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')
                ->disableOriginalConstructor()
                ->getMock();
            $entityMetadata->expects($this->any())
                ->method('getName')
                ->will($this->returnValue($entityClassName));
            $metadata[$entityClassName] = $entityMetadata;

            $fieldNames = [];
            $fieldTypes = [];
            $fieldIdentifiers = [];
            $configs = [];
            foreach ($entityData['fields'] as $fieldName => $fieldData) {
                $fieldNames[] = $fieldName;
                $fieldTypes[] = [$fieldName, $fieldData['type']];
                $fieldIdentifiers[] = [$fieldName, isset($fieldData['identifier']) ? $fieldData['identifier'] : false];
                $configId = new FieldConfigId('extend', $entityClassName, $fieldName, $fieldData['type']);
                $configs[] = new Config($configId);
            }
            $fieldConfigs[$entityClassName] = $configs;
            $entityMetadata->expects($this->any())
                ->method('getFieldNames')
                ->will($this->returnValue($fieldNames));
            $entityMetadata->expects($this->any())
                ->method('isIdentifier')
                ->will($this->returnValueMap($fieldIdentifiers));

            $relNames = [];
            if (isset($entityData['relations'])) {
                $relTargetClasses = [];
                foreach ($entityData['relations'] as $relName => $relData) {
                    $fieldTypes[] = [$relName, $relData['type']];
                    $relNames[] = $relName;
                    $relTargetClasses[] = [$relName, $relData['target_class']];
                }
                $entityMetadata->expects($this->any())
                    ->method('getAssociationTargetClass')
                    ->will($this->returnValueMap($relTargetClasses));
                $entityMetadata->expects($this->any())
                    ->method('getAssociationMappedByTargetField')
                    ->will($this->returnValue('id'));
            }
            $entityMetadata->expects($this->any())
                ->method('getAssociationNames')
                ->will($this->returnValue($relNames));
            if (isset($entityData['unidirectional_relations'])) {
                $mappings = [];
                foreach ($entityData['unidirectional_relations'] as $relName => $relData) {
                    $fieldTypes[] = [$relName, $relData['type']];
                    $relData['fieldName'] = $relName;
                    $relData['isOwningSide'] = true;
                    $relData['inversedBy'] = null;
                    $relData['sourceEntity'] = $entityClassName;
                    unset($relData['config']);
                    $mappings[$relName] = $relData;
                }
                $entityMetadata->expects($this->any())
                    ->method('getAssociationMappings')
                    ->will($this->returnValue($mappings));
            }
            $entityMetadata->expects($this->any())
                ->method('getTypeOfField')
                ->will($this->returnValueMap($fieldTypes));
        }

        $em = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();

        $metadataFactory = $this->getMock('Doctrine\ORM\Mapping\ClassMetadataFactory');
        $em->expects($this->any())
            ->method('getMetadataFactory')
            ->will($this->returnValue($metadataFactory));
        $metadataFactory->expects($this->any())
            ->method('getMetadataFor')
            ->will(
                $this->returnCallback(
                    function ($entityClassName) use (&$metadata) {
                        return $metadata[$entityClassName];
                    }
                )
            );

        $this->doctrine->expects($this->any())
            ->method('getManagerForClass')
            ->with($this->isType('string'))
            ->will($this->returnValue($em));

        $this->extendConfigProvider->expects($this->any())
            ->method('getConfigs')
            ->will(
                $this->returnCallback(
                    function ($className) use ($fieldConfigs) {
                        return $fieldConfigs[$className];
                    }
                )
            );
        $this->entityConfigProvider->expects($this->any())
            ->method('hasConfig')
            ->will(
                $this->returnCallback(
                    function ($className, $fieldName) use (&$config) {
                        if (isset($config[$className])) {
                            if ($fieldName === null) {
                                return true;
                            }
                            if (isset($config[$className]['fields'][$fieldName]['config'])) {
                                return true;
                            }
                            if (isset($config[$className]['relations'][$fieldName]['config'])) {
                                return true;
                            }
                            if (isset($config[$className]['unidirectional_relations'][$fieldName]['config'])) {
                                return true;
                            }
                        }

                        return false;
                    }
                )
            );
        $this->entityConfigProvider->expects($this->any())
            ->method('getConfig')
            ->will(
                $this->returnCallback(
                    function ($className, $fieldName) use (&$config) {
                        if (isset($config[$className])) {
                            if ($fieldName === null) {
                                return $this->getEntityConfig($className, $config[$className]['config']);
                            }
                            if (isset($config[$className]['fields'][$fieldName]['config'])) {
                                return $this->getEntityFieldConfig(
                                    $className,
                                    $fieldName,
                                    $config[$className]['fields'][$fieldName]['type'],
                                    $config[$className]['fields'][$fieldName]['config']
                                );
                            }
                            if (isset($config[$className]['relations'][$fieldName]['config'])) {
                                return $this->getEntityFieldConfig(
                                    $className,
                                    $fieldName,
                                    $config[$className]['relations'][$fieldName]['type'],
                                    $config[$className]['relations'][$fieldName]['config']
                                );
                            }
                            if (isset($config[$className]['unidirectional_relations'][$fieldName]['config'])) {
                                return $this->getEntityFieldConfig(
                                    $className,
                                    $fieldName,
                                    $config[$className]['unidirectional_relations'][$fieldName]['type'],
                                    $config[$className]['unidirectional_relations'][$fieldName]['config']
                                );
                            }
                        }

                        return null;
                    }
                )
            );

        $this->extendConfigProvider->expects($this->any())
            ->method('hasConfig')
            ->will(
                $this->returnCallback(
                    function ($className, $fieldName) use (&$config) {
                        if (isset($config[$className])) {
                            if ($fieldName === null) {
                                return true;
                            }
                            if (isset($config[$className]['fields'][$fieldName]['config'])) {
                                return true;
                            }
                            if (isset($config[$className]['relations'][$fieldName]['config'])) {
                                return true;
                            }
                            if (isset($config[$className]['unidirectional_relations'][$fieldName]['config'])) {
                                return true;
                            }
                        }

                        return false;
                    }
                )
            );
        $this->extendConfigProvider->expects($this->any())
            ->method('getConfig')
            ->will(
                $this->returnCallback(
                    function ($className, $fieldName) use (&$config) {
                        if (isset($config[$className])) {
                            if ($fieldName === null) {
                                return $this->getExtendEntityConfig($className, $config[$className]['config']);
                            }
                            if (isset($config[$className]['fields'][$fieldName]['config'])) {
                                return $this->getExtendFieldConfig(
                                    $className,
                                    $fieldName,
                                    $config[$className]['fields'][$fieldName]['type'],
                                    $config[$className]['fields'][$fieldName]['config']
                                );
                            }
                            if (isset($config[$className]['relations'][$fieldName]['config'])) {
                                return $this->getExtendFieldConfig(
                                    $className,
                                    $fieldName,
                                    $config[$className]['relations'][$fieldName]['type'],
                                    $config[$className]['relations'][$fieldName]['config']
                                );
                            }
                            if (isset($config[$className]['unidirectional_relations'][$fieldName]['config'])) {
                                return $this->getExtendFieldConfig(
                                    $className,
                                    $fieldName,
                                    $config[$className]['unidirectional_relations'][$fieldName]['type'],
                                    $config[$className]['unidirectional_relations'][$fieldName]['config']
                                );
                            }
                        }

                        return null;
                    }
                )
            );

        $this->extendConfigProvider->expects($this->any())
            ->method('getConfigById')
            ->will(
                $this->returnCallback(
                    function (EntityConfigId $configId) use (&$config) {
                        $className = $configId->getClassname();

                        if (isset($config[$className])) {
                            return $this->getExtendEntityConfig($className, $config[$className]['config']);
                        }

                        return null;
                    }
                )
            );

        $this->extendConfigProvider->expects($this->any())
            ->method('getId')
            ->will(
                $this->returnCallback(
                    function ($className, $fieldName) use (&$config) {
                        if (isset($config[$className])) {
                            if (isset($config[$className]['fields'][$fieldName]['config'])) {
                                return new FieldConfigId(
                                    'extend',
                                    $className,
                                    $fieldName,
                                    $config[$className]['fields'][$fieldName]['type']
                                );
                            }
                            if (isset($config[$className]['relations'][$fieldName]['config'])) {
                                return new FieldConfigId(
                                    'extend',
                                    $className,
                                    $fieldName,
                                    $config[$className]['relations'][$fieldName]['type']
                                );
                            }
                            if (isset($config[$className]['unidirectional_relations'][$fieldName]['config'])) {
                                return new FieldConfigId(
                                    'extend',
                                    $className,
                                    $fieldName,
                                    $config[$className]['unidirectional_relations'][$fieldName]['type']
                                );
                            }
                        }

                        return null;
                    }
                )
            );
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function prepareWithRelations()
    {
        $config = [
            'Acme\Entity\Test' => [
                'config' => [
                    'label' => 'Test Label',
                    'plural_label' => 'Test Plural Label',
                    'icon' => 'icon-test',
                ],
                'fields' => [
                    'field1' => [
                        'type' => 'integer',
                        'identifier' => true,
                        'config' => [
                            'label' => 'C',
                        ]
                    ],
                    'field2' => [
                        'type' => 'string',
                        'config' => [
                            'label' => 'B',
                        ]
                    ],
                    'field3' => [
                        'type' => 'string',
                        'config' => [
                            'label' => 'A',
                        ]
                    ],
                    'field4' => [
                        'type' => 'string',
                        'config' => []
                    ],
                ],
                'relations' => [
                    'rel1' => [
                        'target_class' => 'Acme\Entity\Test1',
                        'type' => 'ref-many',
                        'config' => [
                            'label' => 'Rel1',
                        ]
                    ],
                ]
            ],
            'Acme\Entity\Test1' => [
                'config' => [
                    'label' => 'Test1 Label',
                    'plural_label' => 'Test1 Plural Label',
                    'icon' => 'icon-test1',
                ],
                'fields' => [
                    'id' => [
                        'type' => 'integer',
                        'identifier' => true,
                        'config' => [
                            'label' => 'B',
                        ]
                    ],
                    'Test1field2' => [
                        'type' => 'string',
                        'config' => [
                            'label' => 'A',
                        ]
                    ],
                ],
                'relations' => [
                    'rel1' => [
                        'target_class' => 'Acme\Entity\Test11',
                        'type' => 'ref-one',
                        'config' => [
                            'label' => 'Rel11',
                        ]
                    ],
                ]
            ],
            'Acme\Entity\Test11' => [
                'config' => [
                    'label' => 'Test11 Label',
                    'plural_label' => 'Test11 Plural Label',
                    'icon' => 'icon-test11',
                ],
                'fields' => [
                    'id' => [
                        'type' => 'integer',
                        'identifier' => true,
                        'config' => [
                            'label' => 'B',
                        ]
                    ],
                    'Test11field2' => [
                        'type' => 'string',
                        'config' => [
                            'label' => 'A',
                        ]
                    ],
                ],
                'relations' => [
                    'rel1' => [
                        'target_class' => 'Acme\Entity\Test111',
                        'type' => 'ref-one',
                        'config' => [
                            'label' => 'Rel111',
                        ]
                    ],
                ]
            ],
            'Acme\Entity\Test111' => [
                'config' => [
                    'label' => 'Test111 Label',
                    'plural_label' => 'Test111 Plural Label',
                    'icon' => 'icon-test111',
                ],
                'fields' => [
                    'id' => [
                        'type' => 'integer',
                        'identifier' => true,
                        'config' => [
                            'label' => 'B',
                        ]
                    ],
                    'Test111field2' => [
                        'type' => 'string',
                        'config' => [
                            'label' => 'A',
                        ]
                    ],
                ],
            ],
            'Acme\Entity\Test22' => [
                'config' => [
                    'label' => 'Test22 Label',
                    'plural_label' => 'Test22 Plural Label',
                    'icon' => 'icon-test22',
                ],
                'fields' => [
                    'id' => [
                        'type' => 'integer',
                        'identifier' => true,
                        'config' => [
                            'label' => 'B',
                        ]
                    ],
                ],
                'unidirectional_relations' => [
                    'uni_rel1' => [
                        'targetEntity' => 'Acme\Entity\Test1',
                        'type' => 'ref-one',
                        'config' => [
                            'label' => 'UniRel1',
                        ]
                    ],
                ]
            ]
        ];
        $this->prepare($config);
    }

    /**
     * @param string $entityClassName
     * @param mixed $values
     * @return Config
     */
    protected function getEntityConfig($entityClassName, $values)
    {
        $entityConfigId = new EntityConfigId('entity', $entityClassName);
        $entityConfig = new Config($entityConfigId);
        $entityConfig->setValues($values);

        return $entityConfig;
    }

    /**
     * @param string $entityClassName
     * @param string $fieldName
     * @param string $fieldType
     * @param mixed $values
     * @return Config
     */
    protected function getEntityFieldConfig($entityClassName, $fieldName, $fieldType, $values)
    {
        $entityFieldConfigId = new FieldConfigId('entity', $entityClassName, $fieldName, $fieldType);
        $entityFieldConfig = new Config($entityFieldConfigId);
        $entityFieldConfig->setValues($values);

        return $entityFieldConfig;
    }

    /**
     * @param string $entityClassName
     * @param string $values
     * @return Config
     */
    protected function getExtendEntityConfig($entityClassName, $values)
    {
        $entityConfigId = new EntityConfigId('extend', $entityClassName);
        $entityConfig = new Config($entityConfigId);
        $entityConfig->setValues($values);

        return $entityConfig;
    }

    /**
     * @param string $entityClassName
     * @param string $fieldName
     * @param string $fieldType
     * @param mixed $values
     * @return Config
     */
    protected function getExtendFieldConfig($entityClassName, $fieldName, $fieldType, $values)
    {
        $extendFieldConfigId = new FieldConfigId('extend', $entityClassName, $fieldName, $fieldType);
        $extendFieldConfig = new Config($extendFieldConfigId);
        $extendFieldConfig->setValues($values);

        return $extendFieldConfig;
    }

    /**
     * @param string $entityClassName
     * @param array $config
     * @return array
     */
    protected function getEntityIds($entityClassName, $config)
    {
        $result = [];
        foreach ($config[$entityClassName]['fields'] as $fieldName => $fieldConfig) {
            $result[] = new FieldConfigId('entity', $entityClassName, $fieldName, $fieldConfig['type']);
        }

        return $result;
    }
}
