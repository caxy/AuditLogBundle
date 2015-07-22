<?php

namespace Caxy\AuditLogBundle\Model;

interface VersionInterface
{
    /**
     * @return UpdateTypeInterface
     */
    public function getUpdateType();

    /**
     * @param UpdateTypeInterface $updateType
     *
     * @return self
     */
    public function setUpdateType(UpdateTypeInterface $updateType);

    /**
     * @return VersionGroupInterface
     */
    public function getVersionGroup();

    /**
     * @param VersionGroupInterface $versionGroup
     *
     * @return self
     */
    public function setVersionGroup(VersionGroupInterface $versionGroup);

    /**
     * @return int
     */
    public function getId();
}
