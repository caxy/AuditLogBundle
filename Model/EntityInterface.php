<?php

namespace Caxy\AuditLogBundle\Model;

interface EntityInterface
{
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
