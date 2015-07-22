<?php

namespace Caxy\AuditLogBundle\Model;

interface EntityRecordInterface
{
    /**
     * @return EntityInterface
     */
    public function getEntity();

    /**
     * @param EntityInterface $entity
     *
     * @return self
     */
    public function setEntity(EntityInterface $entity);

    /**
     * @return int
     */
    public function getLoggedEntityId();

    /**
     * @param int $loggedEntityId
     *
     * @return self
     */
    public function setLoggedEntityId($loggedEntityId);

    /**
     * @return int
     */
    public function getId();
}
