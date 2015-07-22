<?php

namespace Caxy\AuditLogBundle\Model;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

class UpdateType implements UpdateTypeInterface
{
    protected $id;
    protected $entityRecord;
    protected $type;
    protected $versions;

    const INSERT = 'INS';
    const UPDATE = 'UPD';
    const DELETE = 'DEL';

    /**
     * {@inheritDoc}
     */
    public function getEntityRecord()
    {
        return $this->entityRecord;
    }

    /**
     * {@inheritDoc}
     */
    public function setEntityRecord(EntityRecordInterface $entityRecord)
    {
        $this->entityRecord = $entityRecord;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * {@inheritDoc}
     */
    public function setType($type)
    {
        if (!in_array($type, array(self::INSERT, self::UPDATE, self::DELETE))) {
            throw new \InvalidArgumentException('Invalid type');
        }
        $this->type = $type;

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
    public function getVersions()
    {
        return $this->versions ?: $this->versions = new ArrayCollection();
    }

    /**
     * @param VersionInterface $version
     *
     * @return self
     */
    public function addVersion(VersionInterface $version)
    {
        if (!$this->getVersions()->contains($version)) {
            $this->getVersions()->add($version);
        }

        return $this;
    }

    /**
     * @param VersionInterface $version
     *
     * @return self
     */
    public function removeVersion(VersionInterface $version)
    {
        if ($this->getVersions()->contains($version)) {
            $this->getVersions()->removeElement($version);
        }

        return $this;
    }
}
