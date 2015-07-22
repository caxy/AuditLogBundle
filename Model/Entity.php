<?php

namespace Caxy\AuditLogBundle\Model;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

class Entity implements EntityInterface
{
    protected $id;
    protected $name;
    protected $entityRecords;
    protected $properties;

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Collection
     */
    public function getEntityRecords()
    {
        return $this->entityRecords ?: $this->entityRecords = new ArrayCollection();
    }

    /**
     * @param EntityRecordInterface $entityRecord
     *
     * @return self
     */
    public function addEntityRecord(EntityRecordInterface $entityRecord)
    {
        if (!$this->getEntityRecords()->contains($entityRecord)) {
            $this->getEntityRecords()->add($entityRecord);
        }

        return $this;
    }

    /**
     * @param EntityRecordInterface $entityRecord
     *
     * @return self
     */
    public function removeEntityRecord(EntityRecordInterface $entityRecord)
    {
        if ($this->getEntityRecords()->contains($entityRecord)) {
            $this->getEntityRecords()->removeElement($entityRecord);
        }

        return $this;
    }

    /**
     * @return Collection
     */
    public function getProperties()
    {
        return $this->properties ?: $this->properties = new ArrayCollection();
    }

    /**
     * @param PropertyInterface $property
     *
     * @return self
     */
    public function addProperty(PropertyInterface $property)
    {
        if (!$this->getProperties()->contains($property)) {
            $this->getProperties()->add($property);
        }

        return $this;
    }

    /**
     * @param PropertyInterface $property
     *
     * @return self
     */
    public function removeProperty(PropertyInterface $property)
    {
        if ($this->getProperties()->contains($property)) {
            $this->getProperties()->removeElement($property);
        }

        return $this;
    }
}
