<?php

namespace Caxy\AuditLogBundle\Manager;

use Caxy\AuditLogBundle\Configuration\AuditLogConfiguration;

/**
 * Audit Manager grants access to metadata and configuration
 * and has a factory method for audit queries.
 */
class AuditLogManager
{
    private $config;

    private $metadataFactory;

    /**
     * @param AuditLogConfiguration $config
     */
    public function __construct(AuditLogConfiguration $config)
    {
        $this->config = $config;
        $this->metadataFactory = $config->createMetadataFactory();
    }

    public function getMetadataFactory()
    {
        return $this->metadataFactory;
    }

    public function getConfiguration()
    {
        return $this->config;
    }
}
