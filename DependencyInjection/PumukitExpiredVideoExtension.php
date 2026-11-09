<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\DependencyInjection;

use Pumukit\CoreBundle\DependencyInjection\FrontendAssets;
use Pumukit\ExpiredVideoBundle\Routing\RouteLoader;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoConfigurationService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;

class PumukitExpiredVideoExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        FrontendAssets::register($container, dirname(__DIR__), '@pumukit/expired-video');
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        foreach ($config as $key => $value) {
            $container->setParameter('pumukit_expired_video.'.$key, $value);
        }

        $container->register(RouteLoader::class)
            ->setArguments(['$enabled' => '%pumukit_expired_video.enable%', '$env' => '%kernel.environment%'])
            ->addTag('routing.loader')
        ;

        if (!$config['enable']) {
            return;
        }

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('pumukit_expired_video.yaml');

        $container->setParameter('pumukitschema.external_permissions', array_merge($container->getParameter('pumukitschema.external_permissions'), [
            ['role' => ExpiredVideoConfigurationService::ROLE_ACCESS_EXPIRED_VIDEO, 'description' => 'Access expired video'],
            ['role' => ExpiredVideoConfigurationService::ROLE_UNLIMITED_EXPIRED_VIDEO, 'description' => 'Upload videos without expiration date'],
        ]));
    }
}
