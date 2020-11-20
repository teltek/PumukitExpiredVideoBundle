<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
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
