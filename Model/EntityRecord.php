<?php

namespace Caxy\AuditLogBundle\Model;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

class EntityRecord implements EntityRecordInterface
{
    protected $id;
    protected $entity;
    protected $loggedEntityId;
    protected $updateTypes;

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
    public function getLoggedEntityId()
    {
        return $this->loggedEntityId;
    }

    /**
     * {@inheritDoc}
     */
    public function setLoggedEntityId($loggedEntityId)
    {
        $this->loggedEntityId = $loggedEntityId;

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
    public function getUpdateTypes()
    {
        return $this->updateTypes ?: $this->updateTypes = new ArrayCollection();
    }

    /**
     * @param UpdateTypeInterface $updateType
     *
     * @return self
     */
    public function addUpdateType(UpdateTypeInterface $updateType)
    {
        if (!$this->getUpdateTypes()->contains($updateType)) {
            $this->getUpdateTypes()->add($updateType);
        }

        return $this;
    }

    /**
     * @param UpdateTypeInterface $updateType
     *
     * @return self
     */
    public function removeUpdateType(UpdateTypeInterface $updateType)
    {
        if ($this->getUpdateTypes()->contains($updateType)) {
            $this->getUpdateTypes()->removeElement($updateType);
        }

        return $this;
    }
}
