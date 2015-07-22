<?php

namespace Caxy\AuditLogBundle\Model;

interface PropertyInterface
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
     * @return string
     */
    public function getName();

    /**
     * @param string $name
     *
     * @return self
     */
    public function setName($name);

    /**
     * @return int
     */
    public function getId();
}
