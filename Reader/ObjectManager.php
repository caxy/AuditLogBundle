<?php

namespace Caxy\AuditLogBundle\Reader;

use Caxy\AuditLogBundle\Manager\AuditLogManager;
use Caxy\AuditLogBundle\Reader\Collection\PersistentCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ObjectManagerAware;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Proxy\Proxy;
use Doctrine\ORM\Query;

/**
 * The ObjectManager handles the creation of entity objects and their associations.
 *
 * Most of the functions are based on Doctrine's UnitOfWork and EntityManager classes.
 */
class ObjectManager
{
    /**
     * The EntityManager that is used as reference for class metadata
     * and mappings. Also used to load entities that are not stored
     * in AuditLog.
     *
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * The EventManager used by Doctrine for dispatching events.
     *
     * @var \Doctrine\Common\EventManager
     */
    private $evm;

    /**
     * @var \Caxy\AuditLogBundle\Configuration\AuditLogConfiguration
     */
    private $config;

    /**
     * @var \Caxy\AuditLogBundle\Metadata\MetadataFactory
     */
    private $metadata;

    /**
     * The identity map that holds references to all entities that have an identity.
     * The entities are grouped by their class name and version group ID.
     * Since all classes in a hierarchy must share the same identifier set,
     * we always take the root class name of the hierarchy.
     *
     * @var array
     */
    private $identityMap = array();

    /**
     * Map of all identifiers of entities.
     * Keys are object ids (spl_object_hash).
     *
     * @var array
     */
    private $entityIdentifiers = array();

    public function __construct(EntityManager $em, AuditLogManager $manager)
    {
        $this->em = $em;
        $this->evm = $em->getEventManager();
        $this->config = $manager->getConfiguration();
        $this->metadata = $manager->getMetadataFactory();
    }

    /**
     * Gets the Doctrine EntityManager used by this ObjectManager.
     *
     * @return \Doctrine\ORM\EntityManager
     */
    public function getEntityManager()
    {
        return $this->em;
    }

    /**
     * Gets a new instance of the given class.
     *
     * @param ClassMetadata $class
     *
     * @return \Doctrine\Common\Persistence\ObjectManagerAware|object
     */
    private function newInstance($class)
    {
        $entity = $class->newInstance();

        if ($entity instanceof \Doctrine\Common\Persistence\ObjectManagerAware) {
            $entity->injectObjectManager($this->em, $class);
        }

        return $entity;
    }

    /**
     * Tries to find an entity with the given identifier in the identity map.
     *
     * @param mixed  $id
     * @param string $rootClassName
     * @param int    $versionGroupId
     *
     * @return mixed Returns the entity with the specified identifier if it
     *               exists in ObjectManager, FALSE otherwise.
     */
    public function tryGetById($id, $rootClassName, $versionGroupId)
    {
        $idHash = implode(' ', (array) $id);

        return $this->tryGetByIdHash($idHash, $rootClassName, $versionGroupId);
    }

    /**
     * Tries to get an entity by its identifier hash. If no entity is found for
     * the given has, FALSE is returned.
     *
     * @param string $idHash
     * @param string $rootClassName
     * @param int    $versionGroupId
     *
     * @return object|bool
     */
    public function tryGetByIdHash($idHash, $rootClassName, $versionGroupId)
    {
        if (isset($this->identityMap[$rootClassName][$idHash][$versionGroupId])) {
            return $this->identityMap[$rootClassName][$idHash][$versionGroupId];
        }

        return false;
    }

    /**
     * Based off of \Doctrine\ORM\UnitOfWork::createEntity.
     *
     * Creates an entity. Used for reconstitution of persistent entities.
     *
     * @param string $className      The name of the entity class.
     * @param array  $data           The data for the entity.
     * @param int    $versionGroupId The version group of the entity.
     * @param array  $hints          Any hints to account for during reconstitution/lookup of the entity.
     *
     * @return object The entity instance.
     *
     * @internal Highly performance-sensitive method.
     */
    public function getEntityAtVersionGroup($className, array $data, $versionGroupId, &$hints = array())
    {
        $class = $this->em->getClassMetadata($className);

        if ($class->isIdentifierComposite) {
            $id = array();

            foreach ($class->identifier as $fieldName) {
                $id[$fieldName] = isset($class->associationMappings[$fieldName])
                    ? $data[$class->associationMappings[$fieldName]['joinColumns'][0]['name']]
                    : $data[$fieldName];
            }

            $idHash = implode(' ', $id);
        } else {
            $idHash = isset($class->associationMappings[$class->identifier[0]])
                ? $data[$class->associationMappings[$class->identifier[0]]['joinColumns'][0]['name']]
                : $data[$class->identifier[0]];

            $id = array($class->identifier[0] => $idHash);
        }

        if (isset($this->identityMap[$class->rootEntityName][$idHash][$versionGroupId])) {
            $entity = $this->identityMap[$class->rootEntityName][$idHash][$versionGroupId];
            $oid = spl_object_hash($entity);

            if ($entity instanceof Proxy && !$entity->__isInitialized__) {
                $entity->__isInitialized__ = true;
                $overrideLocalValues = true;
            } else {
                $overrideLocalValues = isset($hints[Query::HINT_REFRESH]);

                // If only a specific entity is set to refresh, check that it's the one
                if (isset($hints[Query::HINT_REFRESH_ENTITY])) {
                    $overrideLocalValues = $hints[Query::HINT_REFRESH_ENTITY] === $entity;
                }
            }

            // inject ObjectManager upon refresh or into just loaded proxies
            if ($entity instanceof ObjectManagerAware) {
                $entity->injectObjectManager($this->em, $class);
            }
        } else {
            $entity = $this->newInstance($class);
            $oid = spl_object_hash($entity);

            $this->entityIdentifiers[$oid][$versionGroupId] = $id;
            $this->identityMap[$class->rootEntityName][$idHash][$versionGroupId] = $entity;

            $overrideLocalValues = true;
        }

        if (!$overrideLocalValues) {
            return $entity;
        }

        foreach ($data as $field => $value) {
            if (isset($class->fieldMappings[$field])) {
                $value = $this->prepareFieldValue($class, $field, $value);
                $class->reflFields[$field]->setValue($entity, $value);
            }
        }

        if (isset($hints[Query::HINT_FORCE_PARTIAL_LOAD])) {
            return $entity;
        }

        // initialize assocations
        foreach ($class->associationMappings as $field => $assoc) {
            // Check if the association is not among the fetch-joined assocations already
            if (isset($hints['fetchAlias']) && isset($hints['fetched'][$hints['fetchAlias']][$field])) {
                continue;
            }

            $targetClass = $this->em->getClassMetadata($assoc['targetEntity']);

            switch (true) {
                // X-TO-ONE
                case ($assoc['type'] & ClassMetadata::TO_ONE):
                    if (!$assoc['isOwningSide']) {
                        // Inverse side of x-to-one can never be lazy
                        $class->reflFields[$field]->setValue($entity, $this->getEntityPersister($assoc['targetEntity'], $versionGroupId)->loadOneToOneEntity($assoc, $entity));

                        continue 2;
                    }

                    $associatedId = array();

                    foreach ($assoc['targetToSourceKeyColumns'] as $targetColumn => $srcColumn) {
                        $joinColumnValue = isset($data[$srcColumn]) ? $data[$srcColumn] : null;

                        if ($joinColumnValue === null && isset($data[$field])) {
                            $joinColumnValue = $data[$field];
                        }

                        if ($joinColumnValue !== null) {
                            if ($targetClass->containsForeignIdentifier) {
                                $associatedId[$targetClass->getFieldForColumn($targetColumn)] = $joinColumnValue;
                            } else {
                                $associatedId[$targetClass->fieldNames[$targetColumn]] = $joinColumnValue;
                            }
                        }
                    }

                    if (!$associatedId || $this->getEntityPersister($assoc['targetEntity'], $versionGroupId)->isEntityDeleted($associatedId)) {
                        // Foreign key is  NULL or the entity is deleted at this version group
                        $class->reflFields[$field]->setValue($entity, null);

                        continue;
                    }

                    if (!isset($hints['fetchMode'][$class->name][$field])) {
                        $hints['fetchMode'][$class->name][$field] = $assoc['fetch'];
                    }

                    // Foreign key is set
                    // Check identity map first
                    $relatedIdHash = implode(' ', $associatedId);

                    switch (true) {
                        case (isset($this->identityMap[$targetClass->rootEntityName][$relatedIdHash][$versionGroupId])):
                            $newValue = $this->identityMap[$targetClass->rootEntityName][$relatedIdHash][$versionGroupId];

                            break;
                        case ($targetClass->subClasses):
                            // if it might be a subtype, it can not be lazy. There isn't even
                            // a way to solve this with deferred eager loading, which means putting
                            // an entity with subclasses to a *-to-one location is really bad! (performance-wise)
                            $newValue = $this->getEntityPersister($assoc['targetEntity'], $versionGroupId)->loadOneToOneEntity($assoc, $entity, $associatedId);
                            break;

                        default:
                            switch (true) {
                                // We are negating the condition here. Other cases will assume it is valid!
                                case ($hints['fetchMode'][$class->name][$field] !== ClassMetadata::FETCH_EAGER):
                                    $newValue = $this->getProxy($assoc['targetEntity'], $associatedId, $versionGroupId);
                                    break;

                                default:
                                    $newValue = $this->getEntityPersister($assoc['targetEntity'], $versionGroupId)->load($associatedId);
                                    break;
                            }

                            $newValueOid = spl_object_hash($newValue);
                            $this->entityIdentifiers[$newValueOid][$versionGroupId] = $associatedId;
                            $this->identityMap[$targetClass->rootEntityName][$relatedIdHash][$versionGroupId] = $newValue;
                            break;
                    }

                    $class->reflFields[$field]->setValue($entity, $newValue);

                    if ($assoc['inversedBy'] && $assoc['type'] & ClassMetadata::ONE_TO_ONE) {
                        $inverseAssoc = $targetClass->associationMappings[$assoc['inversedBy']];
                        $targetClass->reflFields[$inverseAssoc['fieldName']]->setValue($newValue, $entity);
                    }

                    break;
                // X-TO-MANY
                default:
                    // Inject collection
                    $pColl = new PersistentCollection($this->em, $targetClass, new ArrayCollection(), $this, $versionGroupId);
                    $pColl->setOwner($entity, $assoc);
                    $pColl->setInitialized(false);

                    $reflField = $class->reflFields[$field];
                    $reflField->setValue($entity, $pColl);

                    if ($assoc['fetch'] == ClassMetadata::FETCH_EAGER) {
                        $this->loadCollection($pColl);
                        $pColl->takeSnapshot();
                    }

                    break;
            }
        }

        if ($overrideLocalValues) {
            if (isset($class->lifecycleCallbacks[Events::postLoad])) {
                $class->invokeLifecycleCallbacks(Events::postLoad, $entity);
            }

            if ($this->evm->hasListeners(Events::postLoad)) {
                $this->evm->dispatchEvent(Events::postLoad, new LifecycleEventArgs($entity, $this->em));
            }
        }

        return $entity;
    }

    /**
     * @param ClassMetadata $class
     * @param string        $field
     * @param mixed         $value
     */
    protected function prepareFieldValue(ClassMetadata $class, $field, $value)
    {
        if (!isset($class->fieldMappings[$field])) {
            return $value;
        }

        $type = $class->fieldMappings[$field]['type'];

        switch ($type) {
            case Type::DATETIME:
                if (!$value instanceof \DateTime) {
                    if (!empty($value)) {
                        $timestamp = is_numeric($value) ? (int) $value : strtotime($value);

                        if ($timestamp !== false) {
                            $dateTime = new \DateTime();
                            $dateTime->setTimestamp($timestamp);

                            $value = $dateTime;
                        } else {
                            throw new \InvalidArgumentException("Invalid value for DateTime field $field");
                        }
                    } else {
                        $value = null;
                    }
                }
                break;
        }

        return $value;
    }

    /**
     * Initializes (loads) an uninitialized persistent collection of an entity.
     *
     * @param Collection $collection The collection to initialize.
     */
    public function loadCollection(Collection $collection)
    {
        $assoc = $collection->getMapping();
        $persister = $this->getEntityPersister($assoc['targetEntity'], $collection->getVersionGroupId());

        switch ($assoc['type']) {
            case ClassMetadata::ONE_TO_MANY:
                $persister->loadOneToManyCollection($assoc, $collection->getOwner(), $collection);
                break;

            case ClassMetadata::MANY_TO_MANY:
                $persister->loadManyToManyCollection($assoc, $collection->getOwner(), $collection);
                break;
        }
    }

    /**
     * Based on \Doctrine\ORM\Proxy\ProxyFactory::getProxy.
     *
     * Gets a reference Proxy instance for the entity of the given type and identified by
     * the given identifier at the given version group ID.
     *
     * In order to allow the entities created through AuditLog to be lazy-loaded with
     * the correct data, we need to inject our custom persister into Doctrine's Proxy
     * class for the entity.
     *
     * @param string $className
     * @param mixed  $identifier
     * @param int    $versionGroupId
     *
     * @return object
     */
    public function getProxy($className, $identifier, $versionGroupId)
    {
        $proxyNamespace = $this->em->getConfiguration()->getProxyNamespace();
        $fqn = ClassUtils::generateProxyClassName($className, $proxyNamespace);

        if (!class_exists($fqn, false)) {
            // Call ProxyFactory::getProxy to generate and require the Proxy class
            // but we won't use the class returned because we need to pass in our
            // custom persister
            $this->em->getProxyFactory()->getProxy($className, $identifier);
        }

        $entityPersister = $this->getEntityPersister($className, $versionGroupId);
        $classMetadata = $this->em->getClassMetadata($className);

        $proxy = new $fqn($this->createInitializer($classMetadata, $entityPersister), $identifier);

        foreach ($classMetadata->getIdentifierFieldNames() as $idField) {
            if (!isset($identifier[$idField])) {
                throw \Doctrine\Common\Proxy\Exception\OutOfBoundsException::missingPrimaryKeyValue($className, $idField);
            }

            $classMetadata->reflFields[$idField]->setValue($proxy, $identifier[$idField]);
        }

        return $proxy;
    }

    /**
     * Creates a closure capable of initializing a proxy
     *
     * @param \Doctrine\Common\Persistence\Mapping\ClassMetadata $classMetadata
     * @param \Doctrine\ORM\Persisters\Entity\EntityPersister    $entityPersister
     *
     * @return \Closure
     *
     * @throws \Doctrine\ORM\EntityNotFoundException
     */
    private function createInitializer(ClassMetadata $classMetadata, \Caxy\AuditLogBundle\Reader\Persister\BasicEntityPersister $entityPersister)
    {
        return function (Proxy $proxy) use ($entityPersister, $classMetadata) {
            $initializer = $proxy->__getInitializer();
            $cloner      = $proxy->__getCloner();
            $proxy->__setInitializer(null);
            $proxy->__setCloner(null);
            if ($proxy->__isInitialized()) {
                return;
            }
            $properties = $proxy->__getLazyProperties();
            foreach ($properties as $propertyName => $property) {
                if (!isset($proxy->$propertyName)) {
                    $proxy->$propertyName = $properties[$propertyName];
                }
            }
            $proxy->__setInitialized(true);
            $identifier = $classMetadata->getIdentifierValues($proxy);
            if (null === $entityPersister->loadById($identifier, $proxy)) {
                $proxy->__setInitializer($initializer);
                $proxy->__setCloner($cloner);
                $proxy->__setInitialized(false);
                throw EntityNotFoundException::fromClassNameAndIdentifier(
                    $classMetadata->getName(),
                    $this->identifierFlattener->flattenIdentifier($classMetadata, $identifier)
                );
            }
        };
    }

    /**
     * Creates a closure capable of finalizing state a cloned proxy
     *
     * @param \Doctrine\Common\Persistence\Mapping\ClassMetadata $classMetadata
     * @param \Doctrine\ORM\Persisters\Entity\EntityPersister    $entityPersister
     *
     * @return \Closure
     *
     * @throws \Doctrine\ORM\EntityNotFoundException
     */
    private function createCloner(ClassMetadata $classMetadata, \Caxy\AuditLogBundle\Reader\Persister\BasicEntityPersister $entityPersister)
    {
        return function (BaseProxy $proxy) use ($entityPersister, $classMetadata) {
            if ($proxy->__isInitialized()) {
                return;
            }
            $proxy->__setInitialized(true);
            $proxy->__setInitializer(null);
            $class      = $entityPersister->getClassMetadata();
            $identifier = $classMetadata->getIdentifierValues($proxy);
            $original   = $entityPersister->loadById($identifier);
            if (null === $original) {
                throw EntityNotFoundException::fromClassNameAndIdentifier(
                    $classMetadata->getName(),
                    $this->identifierFlattener->flattenIdentifier($classMetadata, $identifier)
                );
            }
            foreach ($class->getReflectionClass()->getProperties() as $property) {
                if ( ! $class->hasField($property->name) && ! $class->hasAssociation($property->name)) {
                    continue;
                }
                $property->setAccessible(true);
                $property->setValue($proxy, $property->getValue($original));
            }
        };
    }

    /**
     * Based on \Doctrine\ORM\UnitOfWork::getEntityPersister.
     *
     * Gets the EntityPersister for an entity.
     *
     * @param string $entityName
     * @param int    $versionGroupId
     *
     * @return \Caxy\AuditLogBundle\Reader\Persister\BasicEntityPersister
     */
    public function getEntityPersister($entityName, $versionGroupId)
    {
        if (isset($this->persisters[$entityName][$versionGroupId])) {
            return $this->persisters[$entityName][$versionGroupId];
        }

        $class = $this->em->getClassMetadata($entityName);

        $persister = new Persister\BasicEntityPersister($this->em, $class, $this, $versionGroupId);

        $this->persisters[$entityName][$versionGroupId] = $persister;

        return $this->persisters[$entityName][$versionGroupId];
    }
}
