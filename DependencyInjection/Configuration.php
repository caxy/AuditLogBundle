<?php

namespace Caxy\AuditLogBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('caxy_audit_log');

        // future values: mongodb, couchdb, propel
        $supportedDrivers = array('orm');

        $rootNode
            ->children()
                ->scalarNode('db_driver')
                    ->defaultValue('orm')
                    ->validate()
                        ->ifNotInArray($supportedDrivers)
                        ->thenInvalid('The driver %s is not supported. Please choose one of '.json_encode($supportedDrivers))
                    ->end()
                    ->cannotBeOverwritten()
                ->end()
                ->scalarNode('model_manager_name')->defaultNull()->end()
                ->arrayNode('audited_entities')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('ignored_entities')
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('table_prefix')
                    ->defaultValue('audit_')
                    ->info('Prefix for the generated audit tables.')
                ->end()
                ->scalarNode('table_suffix')
                    ->defaultValue('')
                    ->info('Suffix for the generated audit tables.')
                ->end()
                ->scalarNode('revision_class')->end()
            ->end();

        return $treeBuilder;
    }
}
