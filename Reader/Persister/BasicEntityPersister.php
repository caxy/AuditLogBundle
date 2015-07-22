<?php

namespace Caxy\AuditLogBundle\Reader\Persister;

use Caxy\AuditLogBundle\Model\PropertyType;
use Caxy\AuditLogBundle\Model\UpdateType;
use Caxy\AuditLogBundle\Reader\Collection\PersistentCollection;
use Caxy\AuditLogBundle\Reader\ObjectManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Proxy\Proxy;
use Doctrine\ORM\Query;
use PDO;

/**
 * Based off of Doctrine's class Doctrine\ORM\Persisters\BasicEntityPersister (Doctrine ORM 2.3).
 *
 * In order to use Doctrine's generated Proxy classes to allow for lazy-loading,
 * it was necessary to implement a persister that has the same loading functions
 * that could be injected into the Proxy class.
 *
 * Reasons we forked this class from Doctrine's instead of inheriting and enhancing:
 *     - The majority of logic in the original functions would need to be changed completely
 *         in order to work with AuditLog, and some of which are private access so we would
 *         need to create new functions that essentially produce similar results, but with
 *         tweaked logic.
 *     - Most functions in the base class would not be used, which would just make the
 *         object larger than it needed to be.
 *     - Since the entities being loaded from AuditLog are read-only, we did not want
 *         the insert/update/delete functions to be available.
 *
 * A persister is always responsible for a single entity type at a single version group.
 *
 * This BasicEntityPersister implementation provides the default behavior for
 * querying entities that are mapped to a single database table (in Doctrine),
 * from the CaxyAuditLog tables at a specific version group.
 *
 * The querying operations invoked, either through direct find requests or lazy-loading,
 * are the following:
 *
 *     - {@link load} : Loads (the state of) a single, managed entity.
 *     - {@link loadOneToOneEntity} : Loads a one/many-to-one entity association (lazy-loading).
 *     - {@link loadOneToManyCollection} : Loads a one-to-many entity association (lazy-loading).
 *     - {@link loadManyToManyCollection} : Loads a many-to-many entity association (lazy-loading).
 */
class BasicEntityPersister
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * Metadata object that describes the mapping of the mapped entity class.
     *
     * @var \Doctrine\ORM\Mapping\ClassMetadata
     */
    protected $class;

    /**
     * @var int
     */
    protected $versionGroupId;

    /**
     * The underlying DBAL Connection of the used EntityManager.
     *
     * @var \Doctrine\DBAL\Connection
     */
    protected $conn;

    /**
     * The database platform.
     *
     * @var [type]
     */
    protected $platform;

    /**
     * The ObjectManager instance.
     *
     * @var \Caxy\AuditLogBundle\Reader\ObjectManager
     */
    protected $om;

    /**
     * Map from class names to the corresponding table name.
     *
     * @var array
     */
    private $tableNames = array();

    /**
     * @param integer $versionGroupId
     */
    public function __construct(EntityManager $em, ClassMetadata $class, ObjectManager $om, $versionGroupId)
    {
        $this->em = $em;
        $this->conn = $em->getConnection();
        $this->platform = $this->conn->getDatabasePlatform();
        $this->class = $class;
        $this->om = $om;
        $this->versionGroupId = $versionGroupId;
    }

    /**
     * @return ClassMetadata
     */
    public function getClassMetadata()
    {
        return $this->class;
    }

    /**
     * Returns an array representing the entity identifier,
     * or false if the $criteria does not contain the
     * identifier fields.
     *
     * @param array $criteria *
     *
     * @return array
     */
    private function getIdentifier(array $criteria)
    {
        $id = array();
        foreach ($this->class->identifier as $field) {
            if (!isset($criteria[$field]) || $criteria[$field] === null) {
                return false;
            }
            $id[$field] = $criteria[$field];
        }

        return $id;
    }

    /**
     * Gets an array of all class names in this entity's
     * inheritance tree: parent classes, sub classes, and self.
     *
     * @return array
     */
    private function getAllClassNames()
    {
        return array_merge(
            array($this->class->name),
            $this->class->subClasses,
            $this->class->parentClasses
        );
    }

    /**
     * Gets the value of $fieldName for the entity at the current
     * version group.
     *
     * @param mixed  $identifier
     * @param string $fieldName  *
     *
     * @return string
     */
    public function getPropertyValue($identifier, $fieldName)
    {
        if (is_array($identifier)) {
            $identifier = reset($identifier);
        }

        $qb = $this->em->createQueryBuilder('cr');

        $qb->select('tc.content AS text_content, bc.content AS blob_content')
            ->from('CaxyAuditLogBundle:ContentRecord', 'cr')
            ->join('CaxyAuditLogBundle:Property', 'p', 'WITH', $qb->expr()->eq('p.name', ':field_name'))
            ->join('CaxyAuditLogBundle:PropertyType', 'pt', 'WITH', $qb->expr()->andX(
                $qb->expr()->eq('pt.property', 'p'),
                $qb->expr()->eq('cr.propertyType', 'pt')
            ))
            ->join('CaxyAuditLogBundle:Entity', 'e', 'WITH', $qb->expr()->in('e.name', ':class_names'))
            ->join('CaxyAuditLogBundle:EntityRecord', 'er', 'WITH', $qb->expr()->andX(
                $qb->expr()->eq('er.entity', 'e'),
                $qb->expr()->eq('er.loggedEntityId', ':entity_id')
            ))
            ->join('CaxyAuditLogBundle:UpdateType', 'ut', 'WITH', $qb->expr()->eq('ut.entityRecord', 'er'))
            ->join('CaxyAuditLogBundle:Version', 'v', 'WITH', $qb->expr()->andX(
                $qb->expr()->eq('v.updateType', 'ut'),
                $qb->expr()->eq('v', 'cr.version')
            ))
            ->join('CaxyAuditLogBundle:VersionGroup', 'vg', 'WITH', $qb->expr()->andX(
                $qb->expr()->eq('vg', 'v.versionGroup'),
                $qb->expr()->lte('vg.id', ':version_group_id')
            ))
            ->leftJoin('CaxyAuditLogBundle:TextContent', 'tc', 'WITH', $qb->expr()->eq('tc.contentRecord', 'cr'))
            ->leftJoin('CaxyAuditLogBundle:BlobContent', 'bc', 'WITH', $qb->expr()->eq('bc.contentRecord', 'cr'))

            ->orderBy('v.id', 'DESC')
            ->setMaxResults(1)
            ->setParameters(array(
                'field_name' => $fieldName,
                'class_names' => $this->getAllClassNames(),
                'entity_id' => $identifier,
                'version_group_id' => $this->versionGroupId,
            ));

        $result = $qb->getQuery()->getResult();

        if (empty($result)) {
            return;
        }

        return isset($result[0]['blob_content']) ? $result[0]['blob_content'] : $result[0]['text_content'];
    }

    /**
     * Loads the data of the entity with $identifier in the form
     * of a mapped array: fieldName => value.
     *
     * @param mixed $identifier *
     *
     * @return array
     */
    public function loadDataByIdentifier($identifier)
    {
        $data = array();
        $class = $this->class;

        // add discriminator column value to data array, if available
        if ($class->inheritanceType !== ClassMetadata::INHERITANCE_TYPE_NONE
            && isset($class->discriminatorColumn)
        ) {
            $discriminatorField = $class->discriminatorColumn['fieldName'];
            $discriminatorValue = $this->getPropertyValue($identifier, $discriminatorField);
            if ($discriminatorValue !== null) {
                $data[$discriminatorField] = $discriminatorValue;

                $className = $class->discriminatorMap[$discriminatorValue];

                if ($class->name !== $className) {
                    $class = $this->em->getClassMetadata($className);
                }
            }
        }

        foreach ($class->fieldNames as $field) {
            $data[$field] = $this->getPropertyValue($identifier, $field);
        }

        foreach ($class->associationMappings as $field => $assoc) {
            if ($assoc['isOwningSide'] && $assoc['type'] & ClassMetadata::TO_ONE) {
                $data[$field] = $this->getPropertyValue($identifier, $field);
            }
        }

        if (!is_array($identifier)) {
            $identifier = array($class->identifier[0] => $identifier);
        }

        // add identifier values to data array
        foreach ($class->identifier as $fieldName) {
            $data[$fieldName] = isset($identifier[$fieldName]) ? $identifier[$fieldName] : null;
        }

        return $data;
    }

    /**
     * Returns true if the entity with given $identifier has
     * been logged in the auditlog tables, and therefore
     * has data available.
     *
     * @param mixed $identifier *
     *
     * @return bool
     */
    private function entityRecordExists($identifier)
    {
        if (is_array($identifier)) {
            $identifier = $identifier[$this->class->identifier[0]];
        }

        $qb = $this->em->createQueryBuilder('er');

        $qb->select('er.id')
            ->from('CaxyAuditLogBundle:EntityRecord', 'er')
            ->join('er.entity', 'e', 'WITH', $qb->expr()->in('e.name', ':classNames'))
            ->where($qb->expr()->eq('er.loggedEntityId', ':entityId'))
            ->setMaxResults(1)
            ->setParameters(array(
                'classNames' => $this->getAllClassNames(),
                'entityId' => $identifier,
            ));

        $result = $qb->getQuery()->getResult();

        return !empty($result);
    }

    /**
     * Checks if the entity is deleted at this version group.
     *
     * @param mixed $identifier
     *
     * @return bool
     */
    public function isEntityDeleted($identifier)
    {
        if (is_array($identifier)) {
            $identifier = $identifier[$this->class->identifier[0]];
        }

        $qb = $this->em->createQueryBuilder();

        $qb->select('ut.type')
            ->from('CaxyAuditLogBundle:UpdateType', 'ut')
            ->join('CaxyAuditLogBundle:Entity', 'e', 'WITH', $qb->expr()->in('e.name', ':classNames'))
            ->join('CaxyAuditLogBundle:EntityRecord', 'er', 'WITH', $qb->expr()->andX(
                $qb->expr()->eq('er', 'ut.entityRecord'),
                $qb->expr()->eq('er.entity', 'e'),
                $qb->expr()->eq('er.loggedEntityId', ':identifier')
            ))
            ->join('CaxyAuditLogBundle:Version', 'v', 'WITH', $qb->expr()->eq('v.updateType', 'ut'))
            ->join('CaxyAuditLogBundle:VersionGroup', 'vg', 'WITH', $qb->expr()->andX(
                $qb->expr()->eq('vg', 'v.versionGroup'),
                $qb->expr()->lte('vg.id', ':versionGroupId')
            ))
            ->orderBy('v.id', 'DESC')
            ->setMaxResults(1)
            ->setParameters(array(
                'classNames' => $this->getAllClassNames(),
                'versionGroupId' => $this->versionGroupId,
                'identifier' => $identifier,
            ));

        $result = $qb->getQuery()->getResult();

        return empty($result) ? false : $result[0]['type'] == UpdateType::DELETE;
    }

    /**
     * Loads the current actual data of the entity with given $identifier
     * from the Doctrine entity table. Returns false if entity not found.
     *
     * @param mixed $identifier *
     *
     * @return array|bool Array of entity data, or false if not found.
     */
    private function loadCurrentData($identifier)
    {
        if (is_array($identifier)) {
            $identifier = $identifier[$this->class->identifier[0]];
        }
        $qb = $this->em->getRepository($this->class->name)->createQueryBuilder('e');

        $qb->select('e')
            ->where($qb->expr()->eq('e.'.$this->class->identifier[0], ':id'))
            ->setParameter('id', $identifier);

        $result = $qb->getQuery()->getArrayResult();

        if (!empty($result)) {
            return $result[0];
        }

        return false;
    }

    /**
     * Expand the parameters from the given criteria and use
     * the correct binding types if found. Returns an array
     * with the parameters as first item, and the types as
     * the second item.     *.
     *
     * @param array $criteria *
     *
     * @return array
     */
    private function expandParameters($criteria)
    {
        $params = $types = array();

        foreach ($criteria as $field => $value) {
            if ($value === null) {
                continue; // skip null values
            }

            $types[] = $this->getType($field, $value);
            $params[] = $this->getValue($field, $value);
        }

        return array($params, $types);
    }

    /**
     * Infer field type to be used by parameter type casting.
     *
     * @param string $field
     * @param mixed  $value
     *
     * @return int
     */
    private function getType($field, $value)
    {
        // Since we store all values as text,
        // we'll force the type to be string or
        // array of strings
        $type = PDO::PARAM_STR;

        if (is_array($value)) {
            $type = Type::getType($type)->getBindingType();
            $type += Connection::ARRAY_PARAM_OFFSET;
        }

        return $type;
    }

    /**
     * Retrieve the parameter value.
     *
     * @param string $field
     * @param mixed  $value
     *
     * @return mixed
     */
    private function getValue($field, $value)
    {
        if (is_array($value)) {
            $newValue = array();

            foreach ($value as $itemValue) {
                $newValue[] = $this->getIndividualValue($field, $itemValue);
            }

            return $newValue;
        }

        return $this->getIndividualValue($field, $value);
    }

    /**
     * Retrieves an individual parameter value.
     *
     * @param string $field
     * @param mixed  $value
     *
     * @return mixed
     */
    private function getIndividualValue($field, $value)
    {
        if (is_object($value) && $this->em->getMetadataFactory()->hasMetadataFor(ClassUtils::getClass($value))) {
            $class = $this->em->getClassMetadata(get_class($value));
            $idValues = $class->getIdentifierValues($value);

            $key = key($idValues);

            if (null !== $key) {
                $value = $idValues[$key];
            }
        } elseif ($value instanceof \DateTime) {
            $value = $value->getTimestamp();
        } else {
            try {
                $value = (string) Type::getType($this->class->getTypeOfField($field))->convertToDatabaseValue($value, $this->platform);
            } catch (\Exception $e) {
            }
        }

        return $value;
    }

    /**
     * Returns the mapped table name for the given class.
     *
     * @param string $className
     *
     * @return string
     */
    private function getTableName($className)
    {
        if (!isset($this->tableNames[$className])) {
            $this->tableNames[$className] = $this->em->getClassMetadata($className)->getTableName();
        }

        return $this->tableNames[$className];
    }

    /**
     * Returns a comma-separated string of class names returned
     * from getAllClassNames, to be used in a SQL query.
     *
     * @return string
     */
    private function getAllClassNamesSQL()
    {
        $names = array();

        foreach ($this->getAllClassNames() as $name) {
            $names[] = $this->conn->quote($name);
        }

        return implode(',', $names);
    }

    /**
     * Gets the SQL string for joining the TextContent table in order
     * to get the property value at the given version group.
     *
     * Because the latest property value for an entity at a version group
     * could be stored at any version group prior, a complicated JOIN query is
     * required to get the values of multiple properties in the same SQL statement.
     *
     * In order for the SQL parameters to match their placeholders, an index is supplied
     * is used in the aliases.
     *
     * @param string $field
     * @param int    $index
     * @param mixed  $id
     *
     * @return string
     */
    private function getPropertyJoinSQL($field, $index, $id = null)
    {
        $contentAlias = 'f'.$index;
        $maxAlias = $contentAlias.'_max';

        if (is_array($id)) {
            $id = $id[$this->class->identifier[0]];
        }

        $filterId = $id !== null;

        $sql = 'INNER JOIN '.$this->getTableName('CaxyAuditLogBundle:TextContent').' '.$contentAlias.' '
            .'INNER JOIN ('
                .'SELECT ut.entity_record_id, MAX(tc.id) AS max_text_content_id '
                .'FROM '.$this->getTableName('CaxyAuditLogBundle:TextContent').' tc '
                .'INNER JOIN '.$this->getTableName('CaxyAuditLogBundle:Entity').' e '
                    .'ON e.name IN ('.$this->getAllClassNamesSQL().') '
                .($filterId
                    ? 'INNER JOIN '.$this->getTableName('CaxyAuditLogBundle:EntityRecord').' er '
                        .'ON er.loggedEntityId = '.$this->conn->quote($id).' '
                        .'AND er.entity_id = e.id '
                    : '')
                .'INNER JOIN '.$this->getTableName('CaxyAuditLogBundle:Property').' p '
                    .'ON p.name = '.$this->conn->quote($field).' '
                    .'AND p.entity_id = e.id '
                .'INNER JOIN '.$this->getTableName('CaxyAuditLogBundle:PropertyType').' pt '
                    .'ON pt.type = '.$this->conn->quote(PropertyType::TEXT).' '
                    .'AND pt.property_id = p.id '
                .'INNER JOIN '.$this->getTableName('CaxyAuditLogBundle:ContentRecord').' cr '
                    .'ON cr.id = tc.content_record_id '
                    .'AND cr.property_type_id = pt.id '
                .'INNER JOIN '.$this->getTableName('CaxyAuditLogBundle:Version').' v '
                    .'ON v.id = cr.version_id '
                .'INNER JOIN '.$this->getTableName('CaxyAuditLogBundle:VersionGroup').' vg '
                    .'ON vg.id <= '.$this->conn->quote($this->versionGroupId, PDO::PARAM_INT).' '
                    .'AND vg.id = v.version_group_id '
                .'INNER JOIN '.$this->getTableName('CaxyAuditLogBundle:UpdateType').' ut '
                    .'ON ut.id = v.update_type_id '
                .($filterId
                    ? 'AND ut.entity_record_id = er.id '
                    : '')
                .'GROUP BY ut.entity_record_id'
            .') '.$maxAlias.' '
                .'ON '.$maxAlias.'.entity_record_id = er.id '
                .'AND '.$maxAlias.'.max_text_content_id = '.$contentAlias.'.id ';

        return $sql;
    }

    /**
     * Gets the identifiers of the entities that match $criteria.
     *
     * TODO: Add limit, offset, and orderBy for this function
     *
     * @param array $criteria
     *
     * @return array
     */
    private function getIdsByCriteria(array $criteria)
    {
        $joinSql = $this->getCriteriaJoinSQL($criteria);

        $conditionSql = $this->getSelectConditionSQL($criteria);

        $sql = 'SELECT er.loggedEntityId AS '.$this->class->identifier[0].' FROM '.$this->getTableName('CaxyAuditLogBundle:EntityRecord').' er '
            .$joinSql
            .($conditionSql ? ' WHERE '.$conditionSql : '');

        list($params, $types) = $this->expandParameters($criteria);

        $stmt = $this->conn->executeQuery(trim($sql), $params, $types);

        return $stmt->fetchAll();
    }

    /**
     * Gets the SQL WHERE clause for matching the $criteria.
     *
     * @param array $criteria
     *
     * @return string
     */
    private function getSelectConditionSQL(array $criteria)
    {
        $conditionSql = '';

        $fieldIndex = 0;

        $placeholder = '?';

        foreach ($criteria as $field => $value) {
            $conditionSql .= $conditionSql ? ' AND ' : '';
            $conditionSql .= 'f'.$fieldIndex++.'.content'.(($value === null) ? ' IS NULL' : ' = '.$placeholder);
        }

        return $conditionSql;
    }

    /**
     * Gets the JOIN SQL in order to lookup the fields in $criteria
     * at the version group.
     *
     * @param array $criteria
     *
     * @return string
     */
    private function getCriteriaJoinSQL(array $criteria)
    {
        $joinSql = '';

        $fieldIndex = 0;

        foreach ($criteria as $field => $value) {
            $joinSql .= $this->getPropertyJoinSQL($field, $fieldIndex++);
        }

        return trim($joinSql);
    }

    /**
     * Loads an entity by a list of field criteria.
     *
     * @param array  $criteria The criteria by which to load the entity.
     * @param object $entity   The entity to load the data into. If not specified,
     *                         a new entity is created.
     * @param array  $assoc    The association that connects the entity to load to another entity, if any.
     * @param array  $hints    Hints for entity creation.
     * @param int    $lockmode
     * @param int    $limit    Limit number of results.
     * @param array  $orderBy  Criteria to order by.
     *
     * @return object The loaded entity instance or NULL if the entity can not be found.
     */
    public function load(array $criteria, $entity = null, $assoc = null, array $hints = array(), $lockmode = 0, $limit = null, array $orderBy = null)
    {
        if ($entity !== null) {
            $hints[Query::HINT_REFRESH] = true;
            $hints[Query::HINT_REFRESH_ENTITY] = $entity;
        }

        if (false === ($id = $this->getIdentifier($criteria))) {
            // find id of the entity at version group
            // based on the criteria given
            $results = $this->getIdsByCriteria($criteria);

            if (empty($results)) {
                return;
            }

            $id = $results[0];
        }

        // Return null if entity is deleted at version group
        if ($this->isEntityDeleted($id)) {
            return;
        }

        if (!(isset($hints[Query::HINT_REFRESH]) && $hints[Query::HINT_REFRESH]) &&
            false !== ($newEntity = $this->om->tryGetById($id, $this->class->rootEntityName, $this->versionGroupId))
        ) {
            return $newEntity;
        }

        if ($this->entityRecordExists($id)) {
            $data = $this->loadDataByIdentifier($id);
        } else {
            $data = $this->loadCurrentData($id);
        }

        if ($this->class->inheritanceType !== ClassMetadataInfo::INHERITANCE_TYPE_NONE) {
            if (isset($data[$this->class->discriminatorColumn['fieldName']])) {
                $className = $this->class->discriminatorMap[$data[$this->class->discriminatorColumn['fieldName']]];
            } elseif (isset($this->class->discriminatorValue)) {
                $className = $this->class->discriminatorMap[$this->class->discriminatorValue];
            } else {
                $className = reset($this->class->discriminatorMap);
            }
        } else {
            $className = $this->class->name;
        }
        $newEntity = $this->om->getEntityAtVersionGroup($className, $data, $this->versionGroupId, $hints);

        return $newEntity;
    }

    /**
     * Loads an entity by identifier.
     *
     * @param array       $identifier   The entity identifier.
     * @param object|null $entity       The entity to load the data into. If not specified, a new entity is created.
     *
     * @return object The loaded and managed entity instance or NULL if the entity can not be found.
     */
    public function loadById(array $identifier, $entity = null)
    {
        return $this->load($identifier, $entity);
    }

    /**
     * Loads an entity of this persister's mapped class as part of a single-valued
     * association from another entity.
     *
     * @param array  $assoc        The association to load.
     * @param object $sourceEntity The entity that owns the association (not necessarily the "owning side").
     * @param array  $identifier   The identifier of the entity to load. Must be provided if
     *                             the association to load represents the owning side, otherwise
     *                             the identifier is derived from the $sourceEntity.
     *
     * @return object The loaded entity instance or NULL if the entity can not be found.
     */
    public function loadOneToOneEntity(array $assoc, $sourceEntity, array $identifier = array())
    {
        if (($foundEntity = $this->om->tryGetById($identifier, $assoc['targetEntity'], $this->versionGroupId)) != false) {
            return $foundEntity;
        }

        $targetClass = $this->em->getClassMetadata($assoc['targetEntity']);

        if ($assoc['isOwningSide']) {
            $isInverseSingleValued = $assoc['inversedBy'] && !$targetClass->isCollectionValuedAssociation($assoc['inversedBy']);

            // Mark inverse side as fetched in the hints, otherwise the UoW would
            // try to load it in a separate query (remember: to-one inverse sides can not be lazy).
            $hints = array();

            if ($isInverseSingleValued) {
                $hints['fetched']['r'][$assoc['inversedBy']] = true;
            }

            $targetEntity = $this->load($identifier, null, $assoc, $hints);

            // Complete bidirectional association, if necessary
            if ($targetEntity !== null && $isInverseSingleValued) {
                $targetClass->reflFields[$assoc['inversedBy']]->setValue($targetEntity, $sourceEntity);
            }
        } else {
            $sourceClass = $this->em->getClassMetadata($assoc['sourceEntity']);
            $owningAssoc = $targetClass->getAssociationMapping($assoc['mappedBy']);

            // TRICKY: since the association is specular source and target are flipped
            foreach ($owningAssoc['targetToSourceKeyColumns'] as $sourceKeyColumn => $targetKeyColumn) {
                if (!isset($sourceClass->fieldNames[$sourceKeyColumn])) {
                    throw MappingException::joinColumnMustPointToMappedField(
                        $sourceClass->name, $sourceKeyColumn
                    );
                }

                $identifier[$assoc['mappedBy']] =
                    $sourceClass->reflFields[$sourceClass->fieldNames[$sourceKeyColumn]]->getValue($sourceEntity);
            }

            $targetEntity = $this->load($identifier, null, $assoc);

            if ($targetEntity !== null) {
                $targetClass->setFieldValue($targetEntity, $assoc['mappedBy'], $sourceEntity);
            }
        }

        return $targetEntity;
    }

    /**
     * Loads a collection of entities in a one-to-many association.
     *
     * @param array                $assoc
     * @param [type]               $sourceEntity
     * @param PersistentCollection $coll         The collection to load/fill.
     *
     * @return array
     */
    public function loadOneToManyCollection(array $assoc, $sourceEntity, PersistentCollection $coll)
    {
        $criteria = array();

        $owningAssoc = $this->class->associationMappings[$assoc['mappedBy']];
        $sourceClass = $this->em->getClassMetadata($assoc['sourceEntity']);

        foreach ($owningAssoc['targetToSourceKeyColumns'] as $sourceKeyColumn => $targetKeyColumn) {
            if ($sourceClass->containsForeignIdentifier) {
                $field = $sourceClass->getFieldForColumn($sourceKeyColumn);
                $value = $sourceClass->reflFields[$field]->getValue($sourceEntity);

                if (isset($sourceClass->associationMappings[$field])) {
                    $value = $this->om->getEntityIdentifier($value);
                    $value = $value[$this->em->getClassMetadata($sourceClass->associationMappings[$field]['targetEntity'])->identifier[0]];
                }

                $criteria[$this->class->getFieldForColumn($targetKeyColumn)] = $value;
            } else {
                $criteria[$this->class->getFieldForColumn($targetKeyColumn)] = $sourceClass->reflFields[$sourceClass->fieldNames[$sourceKeyColumn]]->getValue($sourceEntity);
            }
        }

        $ids = $this->getIdsByCriteria($criteria);

        $entities = array();

        foreach ($ids as $id) {
            $entity = $this->load($id);
            if ($entity) {
                $coll->hydrateAdd($entity);
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    /**
     * Loads a collection of entities of a many-to-many association.
     *
     * @param array                $assoc        The association mapping of the association being loaded.
     * @param object               $sourceEntity The entity that owns the collection.
     * @param PersistentCollection $coll         The collection to fill.
     *
     * @return array
     *
     * @todo Support composite keys
     */
    public function loadManyToManyCollection(array $assoc, $sourceEntity, PersistentCollection $coll)
    {
        $sourceClass = $this->em->getClassMetadata($assoc['sourceEntity']);
        $arrayType = Type::getType(Type::TARRAY);

        $field = $assoc['fieldName'];

        $sourceId = $sourceClass->getIdentifierValues($sourceEntity);

        $fieldValue = $this->om->getEntityPersister($sourceClass->name, $this->versionGroupId)->getPropertyValue($sourceId, $field);

        $entities = array();

        if ($fieldValue) {
            // Use collection IDs from AL
            $ids = $arrayType->convertToPHPValue($fieldValue, $this->platform);
        } else {
            // Collection IDs not stored, so use current set of IDs
            // The only reason we are doing this is because ManyToMany collections were not logging correctly initially
            // @todo Remove this else block when preparing bundle for public distribution
            $qb = $this->em->createQueryBuilder();

            $qb->select('assoc.'.$this->class->identifier[0])
                ->from($sourceClass->name, 'e')
                ->join('e.'.$field, 'assoc')
                ->where($qb->expr()->eq('e.'.$sourceClass->identifier[0], $sourceId[$sourceClass->identifier[0]]));

            $ids = $qb->getQuery()->getResult();
        }

        foreach ($ids as $id) {
            if (!is_array($id)) {
                $id = array($this->class->identifier[0] => $id);
            }
            $entity = $this->load($id);
            if ($entity) {
                $coll->hydrateAdd($entity);
                $entities[] = $entity;
            }
        }

        return $entities;
    }
}
