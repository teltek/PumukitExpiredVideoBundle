<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pumukit\ExpiredVideoBundle\DependencyInjection\PumukitExpiredVideoExtension;
use Pumukit\ExpiredVideoBundle\EventListener\DisabledExpiredVideoListener;
use Pumukit\ExpiredVideoBundle\EventListener\MultimediaObjectListener;
use Pumukit\ExpiredVideoBundle\Services\MenuService;
use Pumukit\ExpiredVideoBundle\Services\MultimediaObjectMenuService;
use Pumukit\ExpiredVideoBundle\Services\SeriesMenuService;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
class PumukitExpiredVideoExtensionTest extends TestCase
{
    public function testTheEnabledBundleRegistersItsMenusListenerAndPermissions(): void
    {
        $container = $this->load(['enable' => true]);

        foreach ([MenuService::class, MultimediaObjectMenuService::class, SeriesMenuService::class, MultimediaObjectListener::class] as $id) {
            $this->assertTrue($container->hasDefinition($id), $id);
        }
        $this->assertCount(2, $container->getParameter('pumukitschema.external_permissions'));
    }

    public function testTheDisabledBundleRemovesItsMenusListenerAndPermissions(): void
    {
        $container = $this->load(['enable' => false]);

        foreach ([MenuService::class, MultimediaObjectMenuService::class, SeriesMenuService::class, MultimediaObjectListener::class] as $id) {
            $this->assertFalse($container->hasDefinition($id), $id);
        }
        $this->assertTrue($container->hasDefinition(DisabledExpiredVideoListener::class));
        $this->assertSame([], $container->getParameter('pumukitschema.external_permissions'));
    }

    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('pumukitschema.external_permissions', []);
        new PumukitExpiredVideoExtension()->load([$config], $container);

        return $container;
    }
}
