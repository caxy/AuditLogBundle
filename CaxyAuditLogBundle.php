<?php

namespace Caxy\AuditLogBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Caxy\AuditLogBundle\DependencyInjection\Compiler\RegisterMappingsPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Doctrine\Bundle\MongoDBBundle\DependencyInjection\Compiler\DoctrineMongoDBMappingsPass;
use Doctrine\Bundle\CouchDBBundle\DependencyInjection\Compiler\DoctrineCouchDBMappingsPass;

class CaxyAuditLogBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $this->addRegisterMappingsPass($container);
    }

    private function addRegisterMappingsPass(ContainerBuilder $container)
    {
        // the base class is only available since symfony 2.3
        $symfonyVersion = class_exists('Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterMappingsPass');

        $mappings = array(
            realpath(__DIR__.'/Resources/config/doctrine/model') => 'Caxy\AuditLogBundle\Model',
        );

        if ($symfonyVersion && class_exists('Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass')) {
            $container->addCompilerPass(
                DoctrineOrmMappingsPass::createYamlMappingDriver(
                    $mappings,
                    array('caxy_audit_log.model_manager_name'),
                    'caxy_audit_log.backend_type_orm',
                    array('CaxyAuditLogBundle' => 'Caxy\AuditLogBundle\Model')
                )
            );
        } else {
            $container->addCompilerPass(RegisterMappingsPass::createOrmMappingDriver($mappings));
        }

        if ($symfonyVersion && class_exists('Doctrine\Bundle\MongoDBBundle\DependencyInjection\Compiler\DoctrineMongoDBMappingsPass')) {
            $container->addCompilerPass(
                DoctrineMongoDBMappingsPass::createYamlMappingDriver(
                    $mappings,
                    array('caxy_audit_log.model_manager_name'),
                    'caxy_audit_log.backend_type_mongodb',
                    array('CaxyAuditLogBundle' => 'Caxy\AuditLogBundle\Model')
                )
            );
        } else {
            $container->addCompilerPass(RegisterMappingsPass::createMongoDBMappingDriver($mappings));
        }

        if ($symfonyVersion && class_exists('Doctrine\Bundle\CouchDBBundle\DependencyInjection\Compiler\DoctrineCouchDBMappingsPass')) {
            $container->addCompilerPass(
                DoctrineCouchDBMappingsPass::createYamlMappingDriver(
                    $mappings,
                    array('caxy_audit_log.model_manager_name'),
                    'caxy_audit_log.backend_type_couchdb',
                    array('CaxyAuditLogBundle' => 'Caxy\AuditLogBundle\Model')
                )
            );
        } else {
            $container->addCompilerPass(RegisterMappingsPass::createCouchDBMappingDriver($mappings));
        }
    }
}
