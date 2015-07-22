<?php

namespace Caxy\AuditLogBundle\Model;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

class Version implements VersionInterface
{
    protected $id;
    protected $updateType;
    protected $versionGroup;
    protected $contentRecords;

    /**
     * {@inheritDoc}
     */
    public function getUpdateType()
    {
        return $this->updateType;
    }

    /**
     * {@inheritDoc}
     */
    public function setUpdateType(UpdateTypeInterface $updateType)
    {
        $this->updateType = $updateType;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getVersionGroup()
    {
        return $this->versionGroup;
    }

    /**
     * {@inheritDoc}
     */
    public function setVersionGroup(VersionGroupInterface $versionGroup)
    {
        $this->versionGroup = $versionGroup;

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
    public function getContentRecords()
    {
        return $this->contentRecords ?: $this->contentRecords = new ArrayCollection();
    }

    /**
     * @param ContentRecordInterface $contentRecord
     *
     * @return self
     */
    public function addContentRecord(ContentRecordInterface $contentRecord)
    {
        if (!$this->getContentRecords()->contains($contentRecord)) {
            $this->getContentRecords()->add($contentRecord);
        }

        return $this;
    }

    /**
     * @param ContentRecordInterface $contentRecord
     *
     * @return self
     */
    public function removeContentRecord(ContentRecordInterface $contentRecord)
    {
        if ($this->getContentRecords()->contains($contentRecord)) {
            $this->getContentRecords()->removeElement($contentRecord);
        }

        return $this;
    }
}
