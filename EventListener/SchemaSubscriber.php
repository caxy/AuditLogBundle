<?php

namespace Caxy\AuditLogBundle\EventListener;

use Caxy\AuditLogBundle\Manager\AuditLogManager;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

class SchemaSubscriber implements EventSubscriber
{
    /**
     * @var \Caxy\AuditLogBundle\AuditLogConfiguration
     */
    protected $config;

    /**
     * @var \Caxy\AuditLogBundle\Metadata\MetadataFactory
     */
    protected $metadataFactory;

    public function __construct(AuditLogManager $manager)
    {
        $this->config = $manager->getConfiguration();
        $this->metadataFactory = $manager->getMetadataFactory();
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscribedEvents()
    {
        return array(
            Events::loadClassMetadata,
        );
    }

    /**
     * @param LoadClassMetadataEventArgs $eventArgs [description]
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {
        $classMetadata = $eventArgs->getClassMetadata();
        if ($classMetadata->namespace != 'Caxy\AuditLogBundle\Model') {
            return;
        }
        if ($classMetadata->isInheritanceTypeSingleTable() && !$classMetadata->isRootEntity()) {
            return;
        }

        $classMetadata->setTableName($this->config->getTablePrefix().$classMetadata->getTableName().$this->config->getTableSuffix());

        foreach ($classMetadata->getAssociationMappings() as $fieldName => $mapping) {
            if ($mapping['type'] == ClassMetadataInfo::MANY_TO_MANY) {
                $mappedTableName = $classMetadata->associationMappings[$fieldName]['joinTable']['name'];
                $classMetadata->associationMappings[$fieldName]['joinTable']['name'] = $this->config->getTablePrefix().$mappedTableName.$this->config->getTableSuffix();
            }
        }
    }
}
