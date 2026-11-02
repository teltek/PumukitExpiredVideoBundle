<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Pumukit\ExpiredVideoBundle\EventListener\DisabledExpiredVideoListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 *
 * @coversNothing
 */
class DisabledExpiredVideoListenerTest extends TestCase
{
    public function testTheRoutesOfTheBundleAreNotFoundWhenItIsDisabled(): void
    {
        $this->expectException(NotFoundHttpException::class);

        new DisabledExpiredVideoListener(false)->onKernelRequest($this->event('pumukit_expired_video_list'));
    }

    public function testOtherRoutesAreNotTouchedWhenItIsDisabled(): void
    {
        $event = $this->event('pumukitnewadmin_mms_index');
        new DisabledExpiredVideoListener(false)->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testTheRoutesOfTheBundleWorkWhenItIsEnabled(): void
    {
        $event = $this->event('pumukit_expired_series_renew_all');
        new DisabledExpiredVideoListener(true)->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    private function event(string $route): RequestEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);

        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
