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
            ->scalarNode('notification_email_subject')
            ->defaultValue('PuMuKIT - These videos will be expired coming soon.')
            ->info('Subject of email on notification command')
            ->end()
            ->scalarNode('notification_email_template')
            ->defaultValue('PumukitExpiredVideoBundle:Email:notification.html.twig')
            ->info('Template of email on notification command')
            ->end()
            ->scalarNode('update_email_subject')
            ->defaultValue('PuMuKIT - Remove owner of the following video.')
            ->info('Subject of email on notification command')
            ->end()
            ->scalarNode('update_email_template')
            ->defaultValue('PumukitExpiredVideoBundle:Email:update_admin_email.html.twig')
            ->info('Subject of email on update command')
            ->end()
            ->arrayNode('administrator_emails')
            ->defaultValue(['youremailaccount@pumukit.es'])
            ->info('Administrator emails to received notifications')
            ->prototype('scalar')
            ->end()
            ->end()
            ->scalarNode('delete_email_subject')
            ->defaultValue('PuMuKIT - Multimedia object deleted')
            ->info('Subject of email on delete command')
            ->end()
            ->scalarNode('delete_email_template')
            ->defaultValue('PumukitExpiredVideoBundle:Email:delete_admin_email.html.twig')
            ->info('Subject of email on update command')
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
