<?php

namespace Caxy\AuditLogBundle\Model;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

class Property implements PropertyInterface
{
    protected $id;
    protected $entity;
    protected $name;
    protected $propertyTypes;
    /**
     * {@inheritDoc}
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * {@inheritDoc}
     */
    public function setEntity(EntityInterface $entity)
    {
        $this->entity = $entity;

        return $this;
    }

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
    public function getPropertyTypes()
    {
        return $this->propertyTypes ?: $this->propertyTypes = new ArrayCollection();
    }

    /**
     * @param PropertyTypeInterface $propertyType
     *
     * @return self
     */
    public function addPropertyType(PropertyTypeInterface $propertyType)
    {
        if (!$this->getPropertyTypes()->contains($propertyType)) {
            $this->getPropertyTypes()->add($propertyType);
        }

        return $this;
    }

    /**
     * @param PropertyTypeInterface $propertyType
     *
     * @return self
     */
    public function removePropertyType(PropertyTypeInterface $propertyType)
    {
        if ($this->getPropertyTypes()->contains($propertyType)) {
            $this->getPropertyTypes()->removeElement($propertyType);
        }

        return $this;
    }
}
