<?php

namespace Caxy\AuditLogBundle\Model;

interface VersionGroupInterface
{
    /**
     * @return \DateTime
     */
    public function getTimestamp();

    /**
     * @param \DateTime $timestamp
     *
     * @return self
     */
    public function setTimestamp(\DateTime $timestamp);

    /**
     * @return int
     */
    public function getUserId();

    /**
     * @param int $userId
     *
     * @return self
     */
    public function setUserId($userId);

    /**
     * @return int
     */
    public function getId();
}
