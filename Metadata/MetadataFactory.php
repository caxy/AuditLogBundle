<?php

namespace Caxy\AuditLogBundle\Metadata;

class MetadataFactory
{
    private $auditedEntities = array();
    private $ignoredEntities = array();

    protected $classNames = array();

    public function __construct($auditedEntities, $ignoredEntities)
    {
        $this->auditedEntities = array_flip($auditedEntities);
        $this->ignoredEntities = array_flip($ignoredEntities);
    }

    public function isAudited($entity)
    {
        if (!empty($this->auditedEntities)) {
            return isset($this->auditedEntities[$entity]);
        }

        return !isset($this->ignoredEntities[$entity]) && strpos($entity, 'Caxy\AuditLogBundle\Model') === false;
    }

    public function getAllClassNames()
    {
        return array_flip($this->auditedEntities);
    }
}
