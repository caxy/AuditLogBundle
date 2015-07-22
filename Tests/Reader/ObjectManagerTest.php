<?php

namespace Caxy\AuditLogBundle\Tests;

use Caxy\AuditLogBundle\Manager\AuditLogManager;
use Caxy\AuditLogBundle\Reader\ObjectManager;
use Caxy\AuditLogBundle\Reader\Persister\BasicEntityPersister;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;

class ObjectManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ObjectManager
     */
    protected $object;

    /**
     * @var EntityManager
     */
    protected $mockEntityManager;

    /**
     * @var ClassMetadata
     */
    protected $mockClassMetadata;

    /**
     * @var AuditLogManager
     */
    protected $mockAuditLogManager;

    protected function setUp()
    {
        $this->mockClassMetadata = $this->createMockClassMetadata();
        $this->mockEntityManager = $this->createMockEntityManager($this->mockClassMetadata);
        $this->mockAuditLogManager = $this->createMockAuditLogManager();

        $this->object = new ObjectManager($this->mockEntityManager, $this->mockAuditLogManager);
        $this->createMockEntityPersister('TestAssociation', $this->object, 1);
    }

    public function testGetEntityAtVersionGroup()
    {
        $testData = array('id' => 1, 'dateField' => '123456789', 'textField' => 'test');

        $expectedDate = new \DateTime();
        $expectedDate->setTimestamp($testData['dateField']);
        $associationEntity = $this->getMock('TestAssociation');

        $dateReflectionMock = $this->createMockReflectionProperty();
        $dateReflectionMock->expects($this->once())
            ->method('setValue')
            ->with($this->anything(), $this->equalTo($expectedDate));

        $textReflectionMock = $this->createMockReflectionProperty();
        $textReflectionMock->expects($this->once())
            ->method('setValue')
            ->with($this->anything(), $this->equalTo($testData['textField']));

        $inverseToOneReflectionMock = $this->createMockReflectionProperty();
        $inverseToOneReflectionMock->expects($this->once())
            ->method('setValue')
            ->with($this->anything(), $associationEntity);

        $this->mockClassMetadata->reflFields = array(
            'dateField' => $dateReflectionMock,
            'textField' => $textReflectionMock,
            'inverseToOne' => $inverseToOneReflectionMock,
        );

        $persister = $this->object->persisters['TestAssociation'][1];
        $persister->expects($this->once())
            ->method('loadOneToOneEntity')
            ->with($this->anything(), $this->isInstanceOf('TestEntity'))
            ->will($this->returnValue($associationEntity));

        $entity = $this->object->getEntityAtVersionGroup('TestEntity', $testData, 1);

        // Verify the object is not reloaded on second call
        $this->assertEquals($entity, $this->object->getEntityAtVersionGroup('TestEntity', array('id' => 1), 1));
    }

    public function testPrepareFieldValueException()
    {
        $this->setExpectedException('\InvalidArgumentException');

        $testData = array('id' => 1, 'dateField' => 'invalid value');
        $this->object->getEntityAtVersionGroup('TestEntity', $testData, 1);
    }

    /**
     * @param string        $className
     * @param ObjectManager $objectManager
     * @param int           $versionGroupId
     *
     * @return BasicEntityPersister
     */
    protected function createMockEntityPersister($className, ObjectManager $objectManager, $versionGroupId)
    {
        $persister = $this->getMockBuilder('\Caxy\AuditLogBundle\Reader\Persister\BasicEntityPersister')
            ->disableOriginalConstructor()
            ->getMock();

        $objectManager->persisters[$className][$versionGroupId] = $persister;

        return $persister;
    }

    /**
     * @return \ReflectionProperty
     */
    protected function createMockReflectionProperty()
    {
        return $this->getMockBuilder('\ReflectionProperty')
            ->setMethods(array('setValue'))
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return AuditLogManager
     */
    protected function createMockAuditLogManager()
    {
        return $this->getMockBuilder('\Caxy\AuditLogBundle\Manager\AuditLogManager')
            ->setMethods(array('getConfiguration', 'getMetadataFactory'))
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return ClassMetadata
     */
    protected function createMockClassMetadata()
    {
        $classMock = $this->getMockBuilder('\Doctrine\ORM\Mapping\ClassMetadata')
            ->disableOriginalConstructor()
            ->getMock();

        $classMock->expects($this->any())
            ->method('isIdentifierComposite')
            ->will($this->returnValue(false));

        $classMock->expects($this->any())
            ->method('newInstance')
            ->will($this->returnValue($this->getMockBuilder('TestEntity')->getMock()));

        $classMock->identifier = array('id');
        $classMock->rootEntityName = 'TestEntity';
        $classMock->name = $classMock->rootEntityName;

        $classMock->fieldMappings = array(
            'dateField' => array('fieldName' => 'dateField', 'type' => Type::DATETIME),
            'textField' => array('fieldName' => 'textField', 'type' => Type::TEXT),
        );

        $classMock->associationMappings = array(
            'inverseToOne' => array(
                'fieldName' => 'inverseToOne',
                'targetEntity' => 'TestAssociation',
                'type' => ClassMetadata::ONE_TO_ONE,
                'isOwningSide' => false,
            ),
        );

        return $classMock;
    }

    /**
     * @param ClassMetadata $classMetadata
     *
     * @return EntityManager
     */
    protected function createMockEntityManager($classMetadata)
    {
        $mock = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->setMethods(array('getClassMetadata', 'getEventManager'))
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects($this->any())
            ->method('getClassMetadata')
            ->will($this->returnValue($classMetadata));

        $eventManagerMock = $this->getMockBuilder('\Doctrine\Common\EventManager')
            ->setMethods(array('hasListeners', 'dispatchEvent'))
            ->disableOriginalConstructor()
            ->getMock();

        $eventManagerMock->expects($this->any())
            ->method('hasListeners')
            ->will($this->returnValue(false));

        $mock->expects($this->any())
            ->method('getEventManager')
            ->will($this->returnValue($eventManagerMock));

        return $mock;
    }
}
