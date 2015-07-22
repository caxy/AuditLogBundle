<?php

namespace Caxy\AuditLogBundle\Reader;

use Caxy\AuditLogBundle\Model\VersionGroup;
use JMS\Serializer\Annotation as JMS;

/**
 * @JMS\ExclusionPolicy("all")
 */
class Revision
{
    protected $class;

    protected $identifier;

    protected $entity;

    protected $versionGroup;

    protected $om;

    protected $persister;

    protected $changedProperties = array();

    protected $entityRevisionNumber;

    public function __construct($class, $identifier, $versionGroup, ObjectManager $om)
    {
        $this->class = $class;
        $this->identifier = $identifier;
        $this->versionGroup = $versionGroup;
        $this->om = $om;

        $this->persister = $this->om->getEntityPersister($this->class->name, $this->getRevisionId());
    }

    public function getEntity()
    {
        if ($this->entity === null) {
            $this->entity = $this->persister->load($this->identifier);
        }

        return $this->entity;
    }

    /**
     * @param string $field
     */
    public function getEntityProperty($field)
    {
        if ($this->entity !== null) {
            return $class->reflFields[$field]->getValue($this->entity);
        }

        return $this->persister->getPropertyValue($this->identifier, $field);
    }

    public function getUserId()
    {
        return $this->versionGroup->getUserId();
    }

    public function getTimestamp()
    {
        return $this->versionGroup->getTimestamp();
    }

    /**
     * @JMS\VirtualProperty
     * @JMS\SerializedName("revision_id")
     * @JMS\Type("integer")
     *
     * @return int
     */
    public function getRevisionId()
    {
        return $this->versionGroup->getId();
    }

    public function isPropertyChanged($field, $className = null, $id = null)
    {
        return in_array($field, $this->getChangedProperties($className, $id));
    }

    public function getChangedProperties($className = null, $id = null)
    {
        if (!$id) {
            if ($className) {
                throw new \InvalidArgumentException('Must include the id parameter if using the className parameter.');
            }

            $className = $this->class->name;
            $id = $this->identifier[$this->class->identifier[0]];
        }

        $em = $this->getEntityManager();

        $class = $em->getClassMetadata($className);

        if (!$class) {
            throw new \InvalidArgumentException('Class metadata not found for class '.$className);
        }

        $className = $class->name;

        $idHash = implode(' ', (array) $id);
        $rootClass = $class->rootEntityName;

        if (isset($this->changedProperties[$rootClass][$idHash])) {
            return $this->changedProperties[$rootClass][$idHash];
        }

        $entityVersions = $em
            ->getRepository('CaxyAuditLogBundle:Version')
            ->getAllForEntityAtRevision($className, $id, $this->getRevisionId());

        $properties = array();
        foreach ($entityVersions as $version) {
            foreach ($version->getContentRecords() as $contentRecord) {
                $propertyType = $contentRecord->getPropertyType();
                $properties[] = $propertyType->getProperty()->getName();
            }
        }

        $this->changedProperties[$rootClass][$idHash] = $properties;

        return $this->changedProperties[$rootClass][$idHash];
    }

    public function getEntityManager()
    {
        return $this->om->getEntityManager();
    }

    public function getEntityRevisionNumber()
    {
        if (!$this->entityRevisionNumber) {
            $countBefore = $this->getEntityManager()
                ->getRepository('CaxyAuditLogBundle:VersionGroup')
                ->getCountBefore(
                    $this->class->name,
                    $this->identifier[$this->class->identifier[0]],
                    $this->getRevisionId()
                );

            $this->entityRevisionNumber = $countBefore + 1;
        }

        return $this->entityRevisionNumber;
    }

    /**
     * Gets the version group for this revision.
     *
     * @return VersionGroup
     */
    public function getVersionGroup()
    {
        return $this->versionGroup;
    }
}
