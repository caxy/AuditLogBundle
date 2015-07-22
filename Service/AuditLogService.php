<?php

namespace Caxy\AuditLogBundle\Service;

use Caxy\AuditLogBundle\Manager\AuditLogManager;
use Caxy\AuditLogBundle\Model\BlobContent;
use Caxy\AuditLogBundle\Model\ContentRecord;
use Caxy\AuditLogBundle\Model\Entity;
use Caxy\AuditLogBundle\Model\EntityRecord;
use Caxy\AuditLogBundle\Model\Property;
use Caxy\AuditLogBundle\Model\PropertyType;
use Caxy\AuditLogBundle\Model\TextContent;
use Caxy\AuditLogBundle\Model\UpdateType;
use Caxy\AuditLogBundle\Model\Version;
use Caxy\AuditLogBundle\Model\VersionGroup;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;

/**
 * Service class that handles the logging of entities
 * and their properties.
 */
class AuditLogService
{
    /**
     * @var Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var Doctrine\DBAL\Connection
     */
    protected $conn;

    /**
     * @var Doctrine\DBAL\Platforms\AbstractPlatform
     */
    protected $platform;

    /**
     * @var Doctrine\ORM\UnitOfWork
     */
    protected $uow;

    /**
     * @var Caxy\AuditLogBundle\Model\VersionGroup
     */
    protected $versionGroup;

    /**
     * @var Caxy\AuditLogBundle\Metadata\MetadataFactory
     */
    protected $metadataFactory;

    /**
     * @var Caxy\AuditLogBundle\Configuration\AuditLogConfiguration
     */
    protected $config;

    /**
     * @var int
     */
    protected $logCount;

    /**
     * @var array
     */
    protected $deletedEntities;

    /**
     * @var Entity[]
     */
    protected $entityTables;

    /**
     * @var array
     */
    protected $entityRepositories;

    /**
     * @var Property[]
     */
    protected $properties;

    /**
     * @var PropertyType[]
     */
    protected $propertyTypes;

    /**
     * @var array
     */
    protected $newObjects;

    /**
     * @var EntityRecord[]
     */
    protected $entityRecords;

    /**
     * @var UpdateType[]
     */
    protected $updateTypes;

    /**
     * Constructor called from an event listener, usually in onFlush event.
     *
     * @param \Doctrine\ORM\EntityManager                  $em
     * @param \Caxy\AuditLogBundle\Manager\AuditLogManager $manager
     */
    public function __construct(EntityManager $em, AuditLogManager $manager)
    {
        $this->em = $em;
        $this->conn = $this->em->getConnection();
        $this->platform = $this->conn->getDatabasePlatform();
        $this->uow = $this->em->getUnitOfWork();
        $this->config = $manager->getConfiguration();
        $this->metadataFactory = $manager->getMetadataFactory();

        // Reset all of the storage properties
        $this->reset();

        $this->createVersionGroup();

        $this->calculateDeletedEntities();
    }

    protected function reset()
    {
        $this->logCount = 0;
        $this->deletedEntities = array();
        $this->entityTables = array();
        $this->entityRepositories = array();
        $this->properties = array();
        $this->propertyTypes = array();
        $this->newObjects = array();
        $this->entityRecords = array();
        $this->updateTypes = array();
    }

    /**
     * Returns the AuditLog entity repository for the given
     * entity name.
     *
     * @param string $entityName
     *
     * @return Doctrine\ORM\EntityRepository
     */
    public function getEntityRepository($entityName)
    {
        $className = 'Caxy\\AuditLogBundle\\Model\\'.$entityName;
        if (!isset($this->entityRepositories[$className])) {
            $this->entityRepositories[$className] = $this->em->getRepository($className);
        }

        return $this->entityRepositories[$className];
    }

    /**
     * Creates a new version group with current timestamp and current user data and
     * sets the versionGroup property to it.
     *
     * @return self
     */
    public function createVersionGroup()
    {
        $versionGroup = new VersionGroup();
        $versionGroup->setTimestamp(new \DateTime());
        $currentUser = $this->config->getCurrentUser();
        if (!empty($currentUser) && is_object($currentUser)) {
            $userMetadata = $this->em->getClassMetadata(get_class($currentUser));
            if (!empty($userMetadata)) {
                // Attempt to get currentUser's ID
                try {
                    $userId = $this->uow->getEntityIdentifier($currentUser);
                    $versionGroup->setUserId($userId[$userMetadata->getSingleIdentifierFieldName()]);
                } catch (\Exception $e) {
                }
            }
        }
        $this->versionGroup = $versionGroup;

        $this->persist($this->versionGroup);

        return $this;
    }

    /**
     * Flushes the entity manager if there are persisted log entries ready to be flushed,
     * and resets deletedEntities, entityTables, and the logCount.
     *
     * It is recommended to call flushAuditLogs in the PostFlush event.
     *
     * @return self
     */
    public function flushAuditLogs()
    {
        $this->deletedEntities = array();
        if ($this->logCount > 0) {
            $this->flush();
        }

        $this->reset();

        return $this;
    }

    protected function flush()
    {
        foreach ($this->newObjects as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
    }

    /**
     * Populate {@link $deletedEntities} with the audited entities scheduled for deletion in the unit of work.
     * Entities are stored as arrays with the entity and its identifier so that the identifier is
     * available in a PostRemove event.
     *
     * Each array added has the following values:
     * - <b>entity</b> (object)
     * The entity to be deleted.
     *
     * - <b>id</b> (array)
     * The array returned from a call to the unit of work's getEntityIdentifier function on the entity.
     *
     * @todo Add reason why this is not done in the postRemove() listener
     *
     * @return int The number of entities added to the deletedEntities array.
     */
    public function calculateDeletedEntities()
    {
        $count = 0;
        foreach ($this->uow->getScheduledEntityDeletions() as $entity) {
            if ($this->isEntityAudited($entity) && $this->addDeletedEntity($entity)) {
                $count++;
            }
        }

        return $count;
    }

    protected function addDeletedEntity($entity)
    {
        $oid = spl_object_hash($entity);
        if (!isset($this->deletedEntities[$oid])) {
            $this->deletedEntities[$oid] = array('entity' => $entity, 'id' => $this->uow->getEntityIdentifier($entity));

            return true;
        }

        return false;
    }

    /**
     * If the entity is configured to be audited, creates log entries for the entity and its data
     * with the update type set to UpdateType::INSERT.
     *
     * @see Caxy\AuditLogBundle\Model\UpdateType::INSERT
     *
     * @param object $entity The entity to log.
     *
     * @return bool Returns true if the entity is configured to be audited.
     */
    public function saveEntityInsert($entity)
    {
        if ($this->isEntityAudited($entity)) {
            $entityData = $this->uow->getEntityChangeSet($entity);
            $this->logEntityChanges($entity, UpdateType::INSERT, $entityData);

            return true;
        }

        return false;
    }

    /**
     * If the entity is configured to be audited, creates log entries for the entity and its data
     * with the update type set to UpdateType::UPDATE.
     *
     * @see Caxy\AuditLogBundle\Model\UpdateType::UPDATE
     *
     * @param object $entity The entity to log.
     *
     * @return bool Returns true if the entity is configured to be audited.
     */
    public function saveEntityUpdate($entity)
    {
        if ($this->isEntityAudited($entity)) {
            $changeSet = $this->uow->getEntityChangeSet($entity);
            $this->logEntityChanges($entity, UpdateType::UPDATE, $changeSet);

            return true;
        }

        return false;
    }

    /**
     * If the entity is configured to be audited, creates a log entry for the entity with
     * the update type set to UpdateType::DELETE.
     *
     * @see Caxy\AuditLogBundle\Model\UpdateType::DELETE
     *
     * @param object $removedEntity The entity to log.
     *
     * @return bool Returns true if the entity is configured to be audited.
     */
    public function saveEntityRemoval($removedEntity)
    {
        $oid = spl_object_hash($removedEntity);
        if ($this->isEntityAudited($removedEntity) && isset($this->deletedEntities[$oid])) {
            $data = $this->deletedEntities[$oid];
            $this->logEntityChanges($data['entity'], UpdateType::DELETE, array('id' => $data['id']));

            return true;
        }

        return false;
    }

    /**
     * Adds an entity to the newObjects array, which will be persisted
     * before flushing changes to the AL entities.
     *
     * @param object $entity
     */
    protected function persist($entity)
    {
        $oid = spl_object_hash($entity);
        if (!isset($this->newObjects[$oid])) {
            $this->newObjects[$oid] = $entity;
        }
    }

    /**
     * Creates log entries for the entity and the properties in the change set array. The changeSet array should
     * have the property field names as keys and an array with the original data and the new data, at index 0 and 1
     * respectively.
     *
     * NOTE: If $updateStr is set to UpdateType::DELETE, $changeSet should be an array representing the entity's
     * identifier and should not follow the 0 and 1 index format described above.
     *
     * The changeSet array can be generated from the unit of work's getEntityChangeSet function.
     *
     * @see Doctrine\ORM\UnitOfWork::getEntityChangeSet()
     *
     * @param object $entity    The entity to log.
     * @param string $updateStr String representing the SQL update type. Should be a constant in UpdateType
     * @param array  $changeSet The changeset array containing the original data and new data for the entity properties
     */
    public function logEntityChanges($entity, $updateStr, array $changeSet)
    {
        $metadata = $this->em->getClassMetadata(get_class($entity));

        $entityTable = $this->getEntityTable($metadata);

        if ($updateStr === UpdateType::DELETE) {
            $entityIdentifier = $changeSet['id'];
        } else {
            $entityIdentifier = $this->uow->getEntityIdentifier($entity);
        }

        $entityRecord = $this->getEntityRecord($entityTable, $metadata, $entityIdentifier);

        $updateType = $this->getUpdateType($entityRecord, $updateStr);

        // create version tracker
        $version = new Version();
        $version->setUpdateType($updateType)
            ->setVersionGroup($this->versionGroup);
        $this->persist($version);

        if ($updateStr !== UpdateType::DELETE) {
            // loop through change set and save revision for each property
            foreach ($changeSet as $fieldName => $fieldChangeSet) {
                $fieldValue = isset($fieldChangeSet[1]) ? $fieldChangeSet[1] : null;
                $this->logEntityProperty($entityTable, $version, $metadata, $fieldName, $fieldValue);
            }

            // record changes to MANY_TO_MANY relationships
            foreach ($metadata->associationMappings as $field => $assoc) {
                if ($assoc['type'] == ClassMetadata::MANY_TO_MANY) {
                    if (($val = $metadata->reflFields[$field]->getValue($entity)) !== null
                        && $val instanceof PersistentCollection
                        && ($val->isDirty() || $updateStr === UpdateType::INSERT)
                    ) {
                        $this->logManyToManyAssociation($entityTable, $version, $metadata, $field, $val);
                    }
                }
            }

            // log discriminator column on insert, if available
            if ($updateStr === UpdateType::INSERT
                && $metadata->inheritanceType !== ClassMetadata::INHERITANCE_TYPE_NONE
                && isset($metadata->discriminatorValue)
            ) {
                $this->logEntityProperty(
                    $entityTable,
                    $version,
                    $metadata,
                    $metadata->discriminatorColumn['fieldName'],
                    $metadata->discriminatorValue,
                    true,
                    $metadata->discriminatorColumn['type']
                );
            }
        }

        $this->logCount++;
    }

    /**
     * Creates a log entry for the ManyToMany association field with the value being an array of IDs of all entities in the collection.
     *
     * @param Entity               $entityTable
     * @param Version              $version
     * @param ClassMetadata        $metadata
     * @param string               $field
     * @param PersistentCollection $coll
     */
    public function logManyToManyAssociation(Entity $entityTable, Version $version, ClassMetadata $metadata, $field, PersistentCollection $coll)
    {
        $class = $coll->getTypeClass();

        $relatedIds = array();

        foreach ($coll as $item) {
            try {
                $id = $class->getIdentifierValues($item);
                if (empty($id)) {
                    $id = $this->uow->getEntityIdentifier($item);
                }
                $relatedIds[] = $id[$class->identifier[0]];
            } catch (\Exception $e) {
                continue;
            }
        }

        $this->logEntityProperty($entityTable, $version, $metadata, $field, $relatedIds, true, Type::TARRAY);
    }

    /**
     * Creates a log entry of the property $fieldName on the entity $entityTable for the Version $version.
     *
     * If $skipAssociationCheck is true, it is assumed that $fieldValue is the identifier for an entity in
     * a TO_MANY association and skip checking if $fieldName is an association. This is used for recursively
     * saving the records of a TO_MANY related field.
     *
     * @param Entity        $entityTable          The AuditLog Entity table for this property
     * @param Version       $version              The Version this log entry is associated to
     * @param ClassMetadata $metadata             The doctrine metadata for the entity class
     * @param string        $fieldName            The name of the property
     * @param mixed         $fieldValue           The property data
     * @param bool          $skipAssociationCheck Skip over association logic in order to set
     *                                            the field directly
     * @param string        $fieldType            The field type used when converting the PHP value
     *                                            to the database value. Constants are available
     *                                            in ClassMetadataInfo for available types.
     */
    public function logEntityProperty(Entity $entityTable, Version $version, ClassMetadata $metadata, $fieldName, $fieldValue, $skipAssociationCheck = false, $fieldType = null)
    {
        if (!$skipAssociationCheck && $metadata->hasAssociation($fieldName)) {
            if ($metadata->isSingleValuedAssociation($fieldName)) {
                // TO_ONE relationship
                $assoc = $metadata->getAssociationMapping($fieldName);
                if ($assoc['isOwningSide']) {
                    $targetClass = $this->em->getClassMetadata($assoc['targetEntity']);
                    if ($fieldValue !== null) {
                        try {
                            $relatedId = $this->uow->getEntityIdentifier($fieldValue);
                            $contentValue = $relatedId[$targetClass->getSingleIdentifierFieldName()];
                        } catch (\Exception $e) {
                            // unable to find the ID for this relationship
                            return;
                        }
                    } else {
                        $contentValue = null;
                    }
                } else {
                    return;
                }
            } else {
                // TO_MANY relationship
                $assoc = $metadata->getAssociationMapping($fieldName);
                $targetClass = $this->em->getClassMetadata($assoc['targetEntity']);
                if ($fieldValue !== null) {
                    foreach ($fieldValue->toArray() as $targetEntity) {
                        if ($targetEntity !== null) {
                            $relatedId = $this->uow->getEntityIdentifier($targetEntity);
                            $targetIdValue = $relatedId[$targetClass->getSingleIdentifierFieldName()];
                        } else {
                            $targetIdValue = null;
                        }
                        $this->logEntityProperty($entityTable, $version, $metadata, $fieldName, $targetIdValue, true);
                    }
                }

                return;
            }
        } elseif ($fieldValue instanceof \DateTime) {
            $contentValue = $fieldValue->getTimestamp();
        } else {
            // If a fieldType is not being forced, then get the field type set for the property in doctrine
            if ($fieldType === null) {
                $fieldType = $metadata->getTypeOfField($fieldName);
            }
            // Try to set convert the value given to a database value
            try {
                $contentValue = (string) Type::getType($fieldType)->convertToDatabaseValue($fieldValue, $this->platform);
            } catch (\Exception $e) {
                return;
            }
        }

        $property = $this->getProperty($entityTable, $fieldName);

        $fieldType = $this->getFieldType($metadata, $fieldName);

        $propertyType = $this->getPropertyType($property, $fieldType);

        $contentRecord = new ContentRecord();
        $contentRecord->setPropertyType($propertyType)
            ->setVersion($version)
            ->setTimestamp(new \DateTime());
        $this->persist($contentRecord);

        switch ($propertyType->getType()) {
            case PropertyType::BLOB:
                $content = new BlobContent();
            break;
            case PropertyType::TEXT:
                $content = new TextContent();
            break;
        }
        $content->setContent($contentValue)
            ->setContentRecord($contentRecord);
        $this->persist($content);
    }

    /**
     * Gets the Entity for a class, or creates and assigns one if it doesn't exist.
     *
     * @param ClassMetadata $metadata The doctrine metadata for the class
     *
     * @return Entity
     */
    protected function getEntityTable(ClassMetadata $metadata)
    {
        if (!isset($this->entityTables[$metadata->name])) {
            $entityTable = $this
                ->getEntityRepository('Entity')
                ->findOneBy(array('name' => $metadata->name));

            if (empty($entityTable)) {
                $entityTable = new Entity();
                $entityTable->setName($metadata->name);
                $this->persist($entityTable);
            }

            $this->entityTables[$metadata->name] = $entityTable;
        }

        return $this->entityTables[$metadata->name];
    }

    /**
     * Gets the EntityRecord based on the class and identifier, or creates and assigns one if it doesn't exist.
     *
     * @param Entity        $entityTable      The Entity to look in
     * @param ClassMetadata $metadata         The class metadata
     * @param array         $entityIdentifier The identifier for the entity
     *
     * @return EntityRecord
     */
    protected function getEntityRecord(Entity $entityTable, ClassMetadata $metadata, array $entityIdentifier)
    {
        $entityId = $entityIdentifier[$metadata->getSingleIdentifierFieldName()];
        $oid = spl_object_hash($entityTable);
        if (!isset($this->entityRecords[$oid][$entityId])) {
            if ($entityTable->getId()) {
                $entityRecord = $this
                    ->getEntityRepository('EntityRecord')
                    ->findOneBy(array('entity' => $entityTable, 'loggedEntityId' => $entityId));
            }

            if (empty($entityRecord)) {
                $entityRecord = new EntityRecord();
                $entityRecord->setEntity($entityTable)
                    ->setLoggedEntityId($entityId);
                $this->persist($entityRecord);
            }

            $this->entityRecords[$oid][$entityId] = $entityRecord;
        }

        return $this->entityRecords[$oid][$entityId];
    }

    /**
     * Gets the UpdateType by type in EntityRecord, or creates and assigns one if it doesn't exist.
     *
     * @param EntityRecord $entityRecord The EntityRecord to look in
     * @param string       $updateStr    The update type to look for
     *
     * @return UpdateType
     */
    protected function getUpdateType(EntityRecord $entityRecord, $updateStr)
    {
        $oid = spl_object_hash($entityRecord);
        if (!isset($this->updateTypes[$oid][$updateStr])) {
            if ($entityRecord->getId()) {
                $updateType = $this->getEntityRepository('UpdateType')
                    ->findOneBy(array('entityRecord' => $entityRecord, 'type' => $updateStr));
            }

            if (empty($updateType)) {
                $updateType = new UpdateType();
                $updateType->setEntityRecord($entityRecord)
                    ->setType($updateStr);
                $this->persist($updateType);
            }

            $this->updateTypes[$oid][$updateStr] = $updateType;
        }

        return $this->updateTypes[$oid][$updateStr];
    }

    /**
     * Gets the Property by fieldName in Entity, or creates and assigns one if it doesn't exist.
     *
     * @param Entity $entityTable The Entity to look in
     * @param string $fieldName   The property name to look for
     *
     * @return Property
     */
    protected function getProperty(Entity $entityTable, $fieldName)
    {
        $oid = spl_object_hash($entityTable);

        if (!isset($this->properties[$oid][$fieldName])) {
            if ($entityTable->getId()) {
                $property = $this
                    ->getEntityRepository('Property')
                    ->findOneBy(array('entity' => $entityTable, 'name' => $fieldName));
            }

            if (empty($property)) {
                $property = new Property();
                $property->setEntity($entityTable);
                $property->setName($fieldName);
                $this->persist($property);
            }

            $this->properties[$oid][$fieldName] = $property;
        }

        return $this->properties[$oid][$fieldName];
    }

    /**
     * Gets the PropertyType by field type in Property, or creates and assigns one if it doesn't exist.
     *
     * @param Property $property  The Property to look in
     * @param string   $fieldType The type to look for
     *
     * @return PropertyType
     */
    protected function getPropertyType(Property $property, $fieldType)
    {
        $oid = spl_object_hash($property);
        if (!isset($this->propertyTypes[$oid][$fieldType])) {
            if ($property->getId()) {
                $propertyType = $this
                    ->getEntityRepository('PropertyType')
                    ->findOneBy(array('property' => $property, 'type' => $fieldType));
            }

            if (empty($propertyType)) {
                $propertyType = new PropertyType();
                $propertyType->setProperty($property);
                $propertyType->setType($fieldType);
                $this->persist($propertyType);
            }

            $this->propertyTypes[$oid][$fieldType] = $propertyType;
        }

        return $this->propertyTypes[$oid][$fieldType];
    }

    /**
     * Gets the property type that the field will be stored as in AuditLog tables.
     *
     * @param ClassMetadata $metadata  The doctrine metadata for the class the property is in
     * @param string        $fieldName The name of the property
     *
     * @return string Returns one of: PropertyType::BLOB, PropertyType::TEXT
     */
    protected function getFieldType(ClassMetadata $metadata, $fieldName)
    {
        $type = $metadata->getTypeOfField($fieldName);

        if ($type === Type::BLOB) {
            return PropertyType::BLOB;
        } else {
            return PropertyType::TEXT;
        }
    }

    /**
     * Checks if an entity is configured to be audited or not.
     *
     * @param object $entity
     *
     * @return bool
     */
    protected function isEntityAudited($entity)
    {
        $metadata = $this->em->getClassMetadata(get_class($entity));

        return $this->metadataFactory->isAudited($metadata->name);
    }
}
