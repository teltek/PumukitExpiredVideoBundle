<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pumukit_expired_video');

        $treeBuilder->getRootNode()
            ->children()
            ->booleanNode('enable')
            ->defaultTrue()
            ->info('If false, nothing expires while the bundle stays registered: the commands and the listener do nothing, its routes return 404 and its menus and permissions disappear')
            ->end()
            ->integerNode('expiration_date_days')
            ->defaultValue(365)
            ->min(1)
            ->info('Days a new multimedia object lasts before it expires, and days added on each renewal')
            ->end()
            ->integerNode('range_warning_days')
            ->defaultValue(90)
            ->min(0)
            ->info('Days ahead shown in the back-office list of videos about to expire')
            ->end()
            ->scalarNode('notification_email_subject')
            ->defaultValue('PuMuKIT - These videos will be expired coming soon.')
            ->info('Subject of the email of the notification command')
            ->end()
            ->scalarNode('notification_email_template')
            ->defaultValue('@PumukitExpiredVideo/Email/notification.html.twig')
            ->info('Template of the email of the notification command')
            ->end()
            ->scalarNode('update_email_subject')
            ->defaultValue('PuMuKIT - Remove owner of the following video.')
            ->info('Subject of the email of the update command')
            ->end()
            ->scalarNode('update_email_template')
            ->defaultValue('@PumukitExpiredVideo/Email/update_admin_email.html.twig')
            ->info('Template of the email of the update command')
            ->end()
            ->arrayNode('administrator_emails')
            ->defaultValue(['youremailaccount@pumukit.es'])
            ->info('Administrator emails that receive the update and delete reports')
            ->scalarPrototype()->end()
            ->end()
            ->scalarNode('delete_email_subject')
            ->defaultValue('PuMuKIT - Multimedia objects deleted')
            ->info('Subject of the email of the delete command')
            ->end()
            ->scalarNode('delete_email_template')
            ->defaultValue('@PumukitExpiredVideo/Email/delete_admin_email.html.twig')
            ->info('Template of the email of the delete command')
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
