<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class DisabledExpiredVideoListener implements EventSubscriberInterface
{
    private const ROUTE_PREFIX = 'pumukit_expired_';

    public function __construct(private readonly bool $isEnabled) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($this->isEnabled) {
            return;
        }

        if (str_starts_with((string) $event->getRequest()->attributes->get('_route'), self::ROUTE_PREFIX)) {
            throw new NotFoundHttpException();
        }
    }
}
