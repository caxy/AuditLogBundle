<?php

namespace Caxy\AuditLogBundle\Configuration;

use Caxy\AuditLogBundle\Metadata\MetadataFactory;

/**
 * The class that provides access to the configuration values of the bundle.
 * It is injected into the bundle services, and its properties are set with
 * the values set in the configuration when initialized as service.
 */
class AuditLogConfiguration
{
    /**
     * @var string
     */
    private $tablePrefix = 'audit_';

    /**
     * @var string
     */
    private $tableSuffix = '';

    /**
     * @var array
     */
    private $auditedEntityClasses = array();

    /**
     * @var array
     */
    private $ignoredEntityClasses = array();

    /**
     * @var mixed
     */
    private $currentUser;

    /**
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }

    /**
     * @param string $tablePrefix
     */
    public function setTablePrefix($tablePrefix)
    {
        $this->tablePrefix = $tablePrefix;
    }

    /**
     * @return string
     */
    public function getTableSuffix()
    {
        return $this->tableSuffix;
    }

    /**
     * @param string $tableSuffix
     */
    public function setTableSuffix($tableSuffix)
    {
        $this->tableSuffix = $tableSuffix;
    }

    /**
     * @param array $classes
     */
    public function setAuditedEntityClasses(array $classes)
    {
        $this->auditedEntityClasses = $classes;
    }

    /**
     * @param array $classes
     */
    public function setIgnoredEntityClasses(array $classes)
    {
        $this->ignoredEntityClasses = $classes;
    }

    /**
     * @param string $class
     */
    public function setRevisionClass($class)
    {
        $this->revisionClass = $class;
    }

    /**
     * @return string
     */
    public function getRevisionClass()
    {
        return $this->revisionClass;
    }

    /**
     * @return MetadataFactory
     */
    public function createMetadataFactory()
    {
        return new MetadataFactory($this->auditedEntityClasses, $this->ignoredEntityClasses);
    }

    /**
     * @return mixed
     */
    public function getCurrentUser()
    {
        return $this->currentUser;
    }

    /**
     * @param mixed $currentUser
     */
    public function setCurrentUser($currentUser)
    {
        $this->currentUser = $currentUser;
    }
}
