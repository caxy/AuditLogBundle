<?php

namespace Caxy\AuditLogBundle\Model;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

class VersionGroup implements VersionGroupInterface
{
    protected $id;
    protected $timestamp;
    protected $userId;
    protected $versions;

    /**
     * {@inheritDoc}
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * {@inheritDoc}
     */
    public function setTimestamp(\DateTime $timestamp)
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * {@inheritDoc}
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;

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
