<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Pumukit\ExpiredVideoBundle\Routing\RouteLoader;

/**
 * @internal
 *
 * @coversNothing
 */
class RouteLoaderTest extends TestCase
{
    public function testNoRouteIsLoadedWhenItIsDisabled(): void
    {
        $this->assertCount(0, new RouteLoader(false)->load('@PumukitExpiredVideoBundle/Controller/', RouteLoader::TYPE));
    }

    public function testItOnlySupportsItsType(): void
    {
        $loader = new RouteLoader(true);

        $this->assertTrue($loader->supports('@PumukitExpiredVideoBundle/Controller/', RouteLoader::TYPE));
        $this->assertFalse($loader->supports('@PumukitExpiredVideoBundle/Controller/', 'attribute'));
    }
}
