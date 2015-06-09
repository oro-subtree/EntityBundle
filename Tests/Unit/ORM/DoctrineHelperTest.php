<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\ORM;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Persistence\ManagerRegistry;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityBundle\Tests\Unit\ORM\Fixtures\TestEntity;
use Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub;
use Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\__CG__\ItemStubProxy;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 */
class DoctrineHelperTest extends \PHPUnit_Framework_TestCase
{
    const TEST_IDENTIFIER = 42;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ManagerRegistry
     */
    protected $registry;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $em;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $classMetadata;

    /**
     * @var DoctrineHelper
     */
    protected $doctrineHelper;

    protected function setUp()
    {
        $this->registry      = $this->getMockBuilder('Doctrine\Common\Persistence\ManagerRegistry')
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->em            = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $this->classMetadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')
            ->disableOriginalConstructor()
            ->getMock();

        $this->doctrineHelper = new DoctrineHelper($this->registry);
    }

    protected function tearDown()
    {
        unset($this->registry);
        unset($this->em);
        unset($this->classMetadata);
        unset($this->doctrineHelper);
    }

    /**
     * @param object|string $entityOrClass
     * @param string        $expectedClass
     * @dataProvider getEntityClassDataProvider
     */
    public function testGetEntityClass($entityOrClass, $expectedClass)
    {
        $this->assertEquals(
            $expectedClass,
            $this->doctrineHelper->getEntityClass($entityOrClass)
        );
    }

    /**
     * @return array
     */
    public function getEntityClassDataProvider()
    {
        return [
            'existing entity'    => [
                'entity'        => new ItemStub(),
                'expectedClass' => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
            ],
            'entity proxy'       => [
                'entity'        => new ItemStubProxy(),
                'expectedClass' => 'ItemStubProxy',
            ],
            'real entity class'  => [
                'entity'        => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
                'expectedClass' => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
            ],
            'proxy entity class' => [
                'entity'        => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\__CG__\ItemStubProxy',
                'expectedClass' => 'ItemStubProxy',
            ],
        ];
    }

    public function testGetEntityIdentifierWithGetIdMethod()
    {
        $identifiers = ['id' => self::TEST_IDENTIFIER];

        $entity = new TestEntity($identifiers['id']);

        $this->registry->expects($this->never())
            ->method('getManagerForClass');

        $this->assertEquals(
            $identifiers,
            $this->doctrineHelper->getEntityIdentifier($entity)
        );
    }

    /**
     * @param object $entity
     * @param string $class
     * @dataProvider getEntityIdentifierDataProvider
     */
    public function testGetEntityIdentifier($entity, $class)
    {
        $identifiers = ['id' => self::TEST_IDENTIFIER];

        $this->classMetadata->expects($this->once())
            ->method('getIdentifierValues')
            ->with($entity)
            ->will($this->returnValue($identifiers));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertEquals(
            $identifiers,
            $this->doctrineHelper->getEntityIdentifier($entity)
        );
    }

    public function testGetEntityIdentifierNotManageableEntity()
    {
        $entity = $this->getMock('FooEntity');

        $this->setExpectedException(
            'Oro\Bundle\EntityBundle\Exception\NotManageableEntityException',
            sprintf('Entity class "%s" is not manageable', get_class($entity))
        );

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(get_class($entity))
            ->will($this->returnValue(null));

        $this->doctrineHelper->getEntityIdentifier($entity);
    }

    /**
     * @return array
     */
    public function getEntityIdentifierDataProvider()
    {
        return [
            'existing entity' => [
                'entity' => new ItemStub(),
                'class'  => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
            ],
            'entity proxy'    => [
                'entity' => new ItemStubProxy(),
                'class'  => 'ItemStubProxy',
            ],
        ];
    }

    /**
     * @param       $expected
     * @param array $identifiers
     * @param bool  $exception
     * @dataProvider getSingleEntityIdentifierDataProvider
     */
    public function testGetSingleEntityIdentifier($expected, array $identifiers, $exception = true)
    {
        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->once())
            ->method('getIdentifierValues')
            ->with($entity)
            ->will($this->returnValue($identifiers));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertEquals(
            $expected,
            $this->doctrineHelper->getSingleEntityIdentifier($entity, $exception)
        );
    }

    /**
     * @return array
     */
    public function getSingleEntityIdentifierDataProvider()
    {
        return [
            'valid identifier'                  => [
                'expected' => self::TEST_IDENTIFIER,
                'actual'   => ['id' => self::TEST_IDENTIFIER],
            ],
            'empty identifier'                  => [
                'expected' => null,
                'actual'   => [],
            ],
            'multiple identifier, no exception' => [
                'expected'  => null,
                'actual'    => ['first_id' => 1, 'second_id' => 2],
                'exception' => false,
            ],
        ];
    }

    /**
     * @expectedException \Oro\Bundle\EntityBundle\Exception\InvalidEntityException
     * @expectedExceptionMessage Can't get single identifier for "ItemStubProxy" entity.
     */
    public function testGetSingleEntityIdentifierIncorrectIdentifier()
    {
        $identifiers = ['key1' => 'value1', 'key2' => 'value2'];

        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->once())
            ->method('getIdentifierValues')
            ->with($entity)
            ->will($this->returnValue($identifiers));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->doctrineHelper->getSingleEntityIdentifier($entity);
    }

    /**
     * @param object $entity
     * @param string $class
     * @dataProvider getEntityIdentifierFieldNamesDataProvider
     */
    public function testGetEntityIdentifierFieldNames($entity, $class)
    {
        $identifiers = ['id' => self::TEST_IDENTIFIER];

        $this->classMetadata->expects($this->any())
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(array_keys($identifiers)));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertEquals(
            array_keys($identifiers),
            $this->doctrineHelper->getEntityIdentifierFieldNames($entity)
        );
    }

    public function testGetEntityIdentifierFieldNamesNotManageableEntity()
    {
        $entity = $this->getMock('FooEntity');

        $this->setExpectedException(
            'Oro\Bundle\EntityBundle\Exception\NotManageableEntityException',
            sprintf('Entity class "%s" is not manageable', get_class($entity))
        );

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(get_class($entity))
            ->will($this->returnValue(null));

        $this->doctrineHelper->getEntityIdentifierFieldNames($entity);
    }

    /**
     * @return array
     */
    public function getEntityIdentifierFieldNamesDataProvider()
    {
        return [
            'existing entity' => [
                'entity' => new ItemStub(),
                'class'  => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
            ],
            'entity proxy'    => [
                'entity' => new ItemStubProxy(),
                'class'  => 'ItemStubProxy',
            ],
        ];
    }

    /**
     * @param       $expected
     * @param array $identifiers
     * @param bool  $exception
     * @dataProvider getSingleEntityIdentifierFieldNameDataProvider
     */
    public function testGetSingleEntityIdentifierFieldName($expected, array $identifiers, $exception = true)
    {
        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->any())
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(array_keys($identifiers)));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertEquals(
            $expected,
            $this->doctrineHelper->getSingleEntityIdentifierFieldName($entity, $exception)
        );
    }

    /**
     * @return array
     */
    public function getSingleEntityIdentifierFieldNameDataProvider()
    {
        return [
            'valid identifier'                  => [
                'expected' => 'id',
                'actual'   => ['id' => self::TEST_IDENTIFIER],
            ],
            'empty identifier'                  => [
                'expected' => null,
                'actual'   => [],
            ],
            'multiple identifier, no exception' => [
                'expected'  => null,
                'actual'    => ['first_id' => 1, 'second_id' => 2],
                'exception' => false,
            ],
        ];
    }

    /**
     * @expectedException \Oro\Bundle\EntityBundle\Exception\InvalidEntityException
     * @expectedExceptionMessage Can't get single identifier field name for "ItemStubProxy" entity.
     */
    public function testGetSingleEntityIdentifierFieldNameIncorrectIdentifier()
    {
        $identifiers = ['key1' => 'value1', 'key2' => 'value2'];

        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->any())
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(array_keys($identifiers)));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->doctrineHelper->getSingleEntityIdentifierFieldName($entity);
    }

    /**
     * @param       $expected
     * @param array $identifiers
     * @param bool  $exception
     * @dataProvider getSingleEntityIdentifierFieldTypeDataProvider
     */
    public function testGetSingleEntityIdentifierFieldType($expected, array $identifiers, $exception = true)
    {
        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->any())
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(array_keys($identifiers)));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));
        $this->classMetadata->expects($this->any())
            ->method('getTypeOfField')
            ->will(
                $this->returnCallback(
                    function ($fieldName) use ($identifiers) {
                        return $identifiers[$fieldName];
                    }
                )
            );

        $this->assertEquals(
            $expected,
            $this->doctrineHelper->getSingleEntityIdentifierFieldType($entity, $exception)
        );
    }

    public function getSingleEntityIdentifierFieldTypeDataProvider()
    {
        return [
            'valid identifier'                  => [
                'expected' => 'integer',
                'actual'   => ['id' => 'integer'],
            ],
            'empty identifier'                  => [
                'expected' => null,
                'actual'   => [],
                'exception' => false,
            ],
            'multiple identifier, no exception' => [
                'expected'  => null,
                'actual'    => ['first_id' => 'integer', 'second_id' => 'string'],
                'exception' => false,
            ],
        ];
    }

    /**
     * @expectedException \Oro\Bundle\EntityBundle\Exception\InvalidEntityException
     * @expectedExceptionMessage Can't get single identifier field type for "ItemStubProxy" entity.
     */
    public function testGetSingleEntityIdentifierFieldTypeIncorrectIdentifier()
    {
        $identifiers = ['key1' => 'integer', 'key2' => 'string'];

        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->once())
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(array_keys($identifiers)));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));
        $this->classMetadata->expects($this->never())
            ->method('getTypeOfField');

        $this->doctrineHelper->getSingleEntityIdentifierFieldType($entity);
    }

    /**
     * @expectedException \Oro\Bundle\EntityBundle\Exception\InvalidEntityException
     * @expectedExceptionMessage Can't get single identifier field type for "ItemStubProxy" entity.
     */
    public function testGetSingleEntityIdentifierFieldTypeEmptyIdentifier()
    {
        $identifiers = [];

        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->once())
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(array_keys($identifiers)));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));
        $this->classMetadata->expects($this->never())
            ->method('getTypeOfField');

        $this->doctrineHelper->getSingleEntityIdentifierFieldType($entity);
    }

    public function testIsManageableEntity()
    {
        $entity = new ItemStubProxy();

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($this->doctrineHelper->getEntityClass($entity))
            ->will($this->returnValue($this->em));

        $this->assertTrue(
            $this->doctrineHelper->isManageableEntity($entity)
        );
    }

    public function testIsManageableEntityForNotManageableEntity()
    {
        $entity = new ItemStubProxy();

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($this->doctrineHelper->getEntityClass($entity))
            ->will($this->returnValue(null));

        $this->assertFalse(
            $this->doctrineHelper->isManageableEntity($entity)
        );
    }

    /**
     * @param mixed $data
     * @param string $class
     *
     * @dataProvider dataProvider
     */
    public function testGetEntityMetadata($data, $class)
    {
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertSame(
            $this->classMetadata,
            $this->doctrineHelper->getEntityMetadata($data)
        );
    }

    /**
     * @expectedException \Oro\Bundle\EntityBundle\Exception\NotManageableEntityException
     * @expectedExceptionMessage Entity class "ItemStubProxy" is not manageable
     */
    public function testGetEntityMetadataNotManageableEntity()
    {
        $class = 'ItemStubProxy';

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue(null));

        $this->doctrineHelper->getEntityMetadata($class);
    }

    /**
     * @param mixed $data
     *
     * @dataProvider dataProvider
     */
    public function testGetEntityManager($data)
    {
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($this->doctrineHelper->getEntityClass($data))
            ->will($this->returnValue($this->em));

        $this->assertSame(
            $this->em,
            $this->doctrineHelper->getEntityManager($data)
        );
    }

    /**
     * @expectedException \Oro\Bundle\EntityBundle\Exception\NotManageableEntityException
     * @expectedExceptionMessage Entity class "ItemStubProxy" is not manageable
     */
    public function testGetEntityManagerNotManageableEntity()
    {
        $class = 'ItemStubProxy';

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue(null));

        $this->doctrineHelper->getEntityManager($class);
    }

    public function testGetEntityRepositoryByEntity()
    {
        $entity      = new ItemStubProxy();
        $entityClass = $this->doctrineHelper->getEntityClass($entity);

        $repo = $this->getMockBuilder('Doctrine\ORM\EntityRepository');

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($entityClass)
            ->will($this->returnValue($this->em));
        $this->em->expects($this->once())
            ->method('getRepository')
            ->with($entityClass)
            ->will($this->returnValue($repo));

        $this->assertSame(
            $repo,
            $this->doctrineHelper->getEntityRepository($entity)
        );
    }

    public function testGetEntityRepositoryByClass()
    {
        $class = 'ItemStubProxy';

        $repo = $this->getMockBuilder('Doctrine\ORM\EntityRepository');

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));
        $this->em->expects($this->once())
            ->method('getRepository')
            ->with($class)
            ->will($this->returnValue($repo));

        $this->assertSame(
            $repo,
            $this->doctrineHelper->getEntityRepository($class)
        );
    }

    public function testGetEntityRepositoryNotManageableEntity()
    {
        $class = 'ItemStubProxy';

        $this->setExpectedException(
            'Oro\Bundle\EntityBundle\Exception\NotManageableEntityException',
            sprintf('Entity class "%s" is not manageable', $class)
        );

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue(null));

        $this->doctrineHelper->getEntityRepository($class);
    }

    public function testGetEntityReference()
    {
        $expectedResult = $this->getMock('MockEntityReference');
        $entityClass    = 'MockEntity';
        $entityId       = 100;

        $this->em->expects($this->once())->method('getReference')
            ->with($entityClass, $entityId)
            ->will($this->returnValue($expectedResult));
        $this->registry->expects($this->any())
            ->method('getManagerForClass')
            ->with($entityClass)
            ->will($this->returnValue($this->em));

        $this->assertEquals(
            $expectedResult,
            $this->doctrineHelper->getEntityReference($entityClass, $entityId)
        );
    }

    public function testGetEntity()
    {
        $expectedResult = new TestEntity();
        $entityClass    = 'MockEntity';
        $entityId       = 100;

        $repo = $this->getMockBuilder('Doctrine\ORM\EntityRepository')
            ->disableOriginalConstructor()
            ->getMock();
        $repo->expects($this->once())
            ->method('find')
            ->with($entityId)
            ->will($this->returnValue($expectedResult));

        $this->em->expects($this->once())
            ->method('getRepository')
            ->with($entityClass)
            ->will($this->returnValue($repo));
        $this->registry->expects($this->any())
            ->method('getManagerForClass')
            ->with($entityClass)
            ->will($this->returnValue($this->em));

        $this->assertSame(
            $expectedResult,
            $this->doctrineHelper->getEntity($entityClass, $entityId)
        );
    }

    public function testCreateEntityInstance()
    {
        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->once())
            ->method('newInstance')
            ->will($this->returnValue($entity));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertSame(
            $entity,
            $this->doctrineHelper->createEntityInstance($class)
        );
    }

    /**
     * @return array
     */
    public function dataProvider()
    {
        return [
            ['ItemStubProxy', 'ItemStubProxy'],
            [new ItemStubProxy(), 'ItemStubProxy']
        ];
    }

    public function testGetSingleRootAlias()
    {
        $qb = $this->getMockBuilder('Doctrine\ORM\QueryBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        $qb->expects($this->once())
            ->method('getRootAliases')
            ->willReturn(['root_alias']);

        $this->assertEquals(
            'root_alias',
            $this->doctrineHelper->getSingleRootAlias($qb)
        );
    }

    // @codingStandardsIgnoreStart
    /**
     * @expectedException \Oro\Bundle\EntityBundle\Exception\InvalidEntityException
     * @expectedExceptionMessage Can't get single root alias for the given query. Reason: the query has several root aliases. "root_alias1, root_alias1".
     */
    // @codingStandardsIgnoreEnd
    public function testGetSingleRootAliasWhenQueryHasSeveralRootAliases()
    {
        $qb = $this->getMockBuilder('Doctrine\ORM\QueryBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        $qb->expects($this->once())
            ->method('getRootAliases')
            ->willReturn(['root_alias1', 'root_alias1']);

        $this->doctrineHelper->getSingleRootAlias($qb);
    }

    // @codingStandardsIgnoreStart
    /**
     * @expectedException \Oro\Bundle\EntityBundle\Exception\InvalidEntityException
     * @expectedExceptionMessage Can't get single root alias for the given query. Reason: the query has no any root aliases.
     */
    // @codingStandardsIgnoreEnd
    public function testGetSingleRootAliasWhenQueryHasNoRootAlias()
    {
        $qb = $this->getMockBuilder('Doctrine\ORM\QueryBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        $qb->expects($this->once())
            ->method('getRootAliases')
            ->willReturn([]);

        $this->doctrineHelper->getSingleRootAlias($qb);
    }

    public function testGetSingleRootAliasWhenQueryHasNoRootAliasAndNoExceptionRequested()
    {
        $qb = $this->getMockBuilder('Doctrine\ORM\QueryBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        $qb->expects($this->once())
            ->method('getRootAliases')
            ->willReturn([]);

        $this->assertNull($this->doctrineHelper->getSingleRootAlias($qb, false));
    }

    /**
     * @dataProvider getPageOffsetProvider
     */
    public function testGetPageOffset($expectedOffset, $page, $limit)
    {
        $this->assertSame($expectedOffset, $this->doctrineHelper->getPageOffset($page, $limit));
    }

    public function getPageOffsetProvider()
    {
        return [
            [0, null, null],
            [0, null, 5],
            [0, 2, null],
            [0, 1, 5],
            [5, 2, 5],
            [0, '2', null],
            [0, '1', '5'],
            [5, '2', '5']
        ];
    }

    public function testApplyEmptyJoins()
    {
        $qb = $this->getMockBuilder('Doctrine\ORM\QueryBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        $qb->expects($this->never())
            ->method('distinct');
        $qb->expects($this->never())
            ->method('getRootAliases');

        $this->doctrineHelper->applyJoins($qb, []);
    }

    public function testApplyJoins()
    {
        $joins = [
            'emails'   => null,
            'phones',
            'contacts' => [],
            'accounts' => [
                'join' => 'accounts_field'
            ],
            'users'    => [
                'join'          => 'accounts.users_field',
                'condition'     => 'users.active = true',
                'conditionType' => 'WITH'
            ]
        ];

        $qb = $this->getMockBuilder('Doctrine\ORM\QueryBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        $qb->expects($this->once())
            ->method('distinct')
            ->with(true);
        $qb->expects($this->once())
            ->method('getRootAliases')
            ->willReturn(['root_alias']);
        $qb->expects($this->at(2))
            ->method('leftJoin')
            ->with('root_alias.emails', 'emails');
        $qb->expects($this->at(3))
            ->method('leftJoin')
            ->with('root_alias.phones', 'phones');
        $qb->expects($this->at(4))
            ->method('leftJoin')
            ->with('root_alias.contacts', 'contacts');
        $qb->expects($this->at(5))
            ->method('leftJoin')
            ->with('root_alias.accounts_field', 'accounts');
        $qb->expects($this->at(6))
            ->method('leftJoin')
            ->with(
                'accounts.users_field',
                'users',
                'WITH',
                'users.active = true'
            );

        $this->doctrineHelper->applyJoins($qb, $joins);
    }

    public function testNormalizeNullCriteria()
    {
        $this->assertEquals(
            new Criteria(),
            $this->doctrineHelper->normalizeCriteria(null)
        );
    }

    public function testNormalizeEmptyCriteria()
    {
        $this->assertEquals(
            new Criteria(),
            $this->doctrineHelper->normalizeCriteria([])
        );
    }

    public function testNormalizeCriteriaObject()
    {
        $criteria = new Criteria();
        $this->assertSame(
            $criteria,
            $this->doctrineHelper->normalizeCriteria($criteria)
        );
    }

    public function testNormalizeCriteriaArray()
    {
        $expectedCriteria = new Criteria();
        $expectedCriteria->andWhere(Criteria::expr()->eq('field', 'value'));

        $this->assertEquals(
            $expectedCriteria,
            $this->doctrineHelper->normalizeCriteria(['field' => 'value'])
        );
    }
}
