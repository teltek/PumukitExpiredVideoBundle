<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class PumukitExpiredVideoExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('pumukit_expired_video.expiration_date_days', $config['expiration_date_days']);
        $container->setParameter('pumukit_expired_video.range_warning_days', $config['range_warning_days']);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $permissions = [['role' => 'ROLE_ACCESS_EXPIRED_VIDEO', 'description' => 'Access expired video']];
        $newPermissions = array_merge($container->getParameter('pumukitschema.external_permissions'), $permissions);
        $container->setParameter('pumukitschema.external_permissions', $newPermissions);

        $permissions = [['role' => 'ROLE_UNLIMITED_EXPIRED_VIDEO', 'description' => 'Upload videos without expiration date']];
        $newPermissions = array_merge($container->getParameter('pumukitschema.external_permissions'), $permissions);
        $container->setParameter('pumukitschema.external_permissions', $newPermissions);
    }
}
