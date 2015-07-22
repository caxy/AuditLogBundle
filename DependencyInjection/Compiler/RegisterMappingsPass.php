<?php

namespace Caxy\AuditLogBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;

/**
 * Forward compatibility class in case CaxyAuditLogBundle is used with older
 * versions of Symfony2 or the doctrine bundles that do not provide the
 * register mappings compiler pass yet.
 *
 * @deprecated Compatibility class to make the bundle work with Symfony < 2.3.
 * To be removed when this bundle drops support for Symfony < 2.3
 *
 * @author David Buchmann <david@liip.ch>
 */
class RegisterMappingsPass implements CompilerPassInterface
{
    private $driver;
    private $driverPattern;
    private $namespaces;
    private $enabledParameter;
    private $fallbackManagerParameter;

    public function __construct($driver, $driverPattern, $namespaces, $enabledParameter, $fallbackManagerParameter)
    {
        $this->driver = $driver;
        $this->driverPattern = $driverPattern;
        $this->namespaces = $namespaces;
        $this->enabledParameter = $enabledParameter;
        $this->fallbackManagerParameter = $fallbackManagerParameter;
    }

    /**
     * Register mappings with the metadata drivers.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter($this->enabledParameter)) {
            return;
        }

        $chainDriverDefService = $this->getChainDriverServiceName($container);
        $chainDriverDef = $container->getDefinition($chainDriverDefService);
        foreach ($this->namespaces as $namespace) {
            $chainDriverDef->addMethodCall('addDriver', array($this->driver, $namespace));
        }
    }

    protected function getChainDriverServiceName(ContainerBuilder $container)
    {
        foreach (array('caxy_audit_log.model_manager_name', $this->fallbackManagerParameter) as $param) {
            if ($container->hasParameter($param)) {
                $name = $container->getParameter($param);
                if ($name) {
                    return sprintf($this->driverPattern, $name);
                }
            }
        }

        throw new ParameterNotFoundException('None of the managerParameters resulted in a valid name');
    }

    public static function createOrmMappingDriver(array $mappings)
    {
        $arguments = array($mappings, '.orm.yml');
        $locator = new Definition('Doctrine\Common\Persistence\Mapping\Driver\SymfonyFileLocator', $arguments);
        $driver = new Definition('Doctrine\ORM\Mapping\Driver\YamlDriver', array($locator));

        return new self($driver, 'doctrine.orm.%s_metadata_driver', $mappings, 'caxy_audit_log.backend_type_orm', 'doctrine.default_entity_manager');
    }

    public static function createMongoDBMappingDriver($mappings)
    {
        $arguments = array($mappings, '.mongodb.yml');
        $locator = new Definition('Doctrine\Common\Persistence\Mapping\Driver\SymfonyFileLocator', $arguments);
        $driver = new Definition('Doctrine\ODM\MongoDB\Mapping\Driver\YamlDriver', array($locator));

        return new self($driver, 'doctrine_mongodb.odm.%s_metadata_driver', $mappings, 'caxy_audit_log.backend_type_mongodb', 'doctrine_mongodb.odm.default_document_manager');
    }

    public static function createCouchDBMappingDriver($mappings)
    {
        $arguments = array($mappings, '.couchdb.yml');
        $locator = new Definition('Doctrine\Common\Persistence\Mapping\Driver\SymfonyFileLocator', $arguments);
        $driver = new Definition('Doctrine\ODM\CouchDB\Mapping\Driver\YamlDriver', array($locator));

        return new self($driver, 'doctrine_couchdb.odm.%s_metadata_driver', $mappings, 'caxy_audit_log.backend_type_couchdb', 'doctrine_couchdb.default_document_manager');
    }
}
