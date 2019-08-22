<?php

namespace Pumukit\ExpiredVideoBundle\DependencyInjection;

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
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('pumukit_expired_video');

        $rootNode
            ->children()
            ->scalarNode('expiration_date_days')
            ->defaultValue(365)
            ->info('Time of the first expiration_date')
            ->end()
            ->scalarNode('range_warning_days')
            ->defaultValue(90)
            ->info('Number of days to warning')
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
