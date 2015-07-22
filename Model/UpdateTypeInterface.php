<?php

namespace Caxy\AuditLogBundle\Model;

interface UpdateTypeInterface
{
    /**
     * @return EntityRecordInterface
     */
    public function getEntityRecord();

    /**
     * @param EntityRecordInterface $entityRecord
     *
     * @return self
     */
    public function setEntityRecord(EntityRecordInterface $entityRecord);

    /**
     * @return string
     */
    public function getType();

    /**
     * @param string $type
     *
     * @throws \InvalidArgumentException If $type is not one of the constants defined in class
     *
     * @return self
     */
    public function setType($type);

    /**
     * @return int
     */
    public function getId();
}
