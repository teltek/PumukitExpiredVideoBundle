<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pumukit\ExpiredVideoBundle\Command\ExpiredVideoUpdateCommand;
use Pumukit\ExpiredVideoBundle\DependencyInjection\PumukitExpiredVideoExtension;
use Pumukit\ExpiredVideoBundle\EventListener\MultimediaObjectListener;
use Pumukit\ExpiredVideoBundle\Routing\RouteLoader;
use Pumukit\ExpiredVideoBundle\Services\MenuService;
use Pumukit\ExpiredVideoBundle\Services\MultimediaObjectMenuService;
use Pumukit\ExpiredVideoBundle\Services\MultimediaObjectVoter;
use Pumukit\ExpiredVideoBundle\Services\SeriesMenuService;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
class PumukitExpiredVideoExtensionTest extends TestCase
{
    public function testTheEnabledBundleRegistersItsServicesAndPermissions(): void
    {
        $container = $this->load(['enable' => true]);

        foreach ([MenuService::class, MultimediaObjectMenuService::class, SeriesMenuService::class, MultimediaObjectListener::class, MultimediaObjectVoter::class, ExpiredVideoUpdateCommand::class, RouteLoader::class] as $id) {
            $this->assertTrue($container->hasDefinition($id), $id);
        }
        $this->assertCount(2, $container->getParameter('pumukitschema.external_permissions'));
    }

    public function testTheDisabledBundleOnlyRegistersTheRouteLoader(): void
    {
        $container = $this->load(['enable' => false]);

        $this->assertSame([RouteLoader::class], $this->expiredVideoServices($container));
        $this->assertSame('%pumukit_expired_video.enable%', $container->getDefinition(RouteLoader::class)->getArgument('$enabled'));
        $this->assertTrue($container->getDefinition(RouteLoader::class)->hasTag('routing.loader'));
        $this->assertSame([], $container->getParameter('pumukitschema.external_permissions'));
    }

    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('pumukitschema.external_permissions', []);
        new PumukitExpiredVideoExtension()->load([$config], $container);

        return $container;
    }

    private function expiredVideoServices(ContainerBuilder $container): array
    {
        return array_values(array_filter(array_keys($container->getDefinitions()), static fn (string $id): bool => str_starts_with($id, 'Pumukit\ExpiredVideoBundle\\')));
    }
}
