<?php

namespace Caxy\AuditLogBundle\Model;

interface PropertyTypeInterface
{
    /**
     * @return PropertyInterface
     */
    public function getProperty();

    /**
     * @param PropertyInterface $property
     *
     * @return self
     */
    public function setProperty(PropertyInterface $property);

    /**
     * @return string
     */
    public function getType();

    /**
     * @param string $type
     *
     * @throws \InvalidArgumentException If $type is not one of the defined constants
     *
     * @return self
     */
    public function setType($type);

    /**
     * @return int
     */
    public function getId();
}
