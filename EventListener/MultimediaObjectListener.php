<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\EventListener;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoConfigurationService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Event\MultimediaObjectCloneEvent;
use Pumukit\SchemaBundle\Event\MultimediaObjectEvent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MultimediaObjectListener
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly ExpiredVideoConfigurationService $expiredVideoConfigurationService,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {}

    public function onMultimediaObjectCreate(MultimediaObjectEvent $event): void
    {
        $multimediaObject = $event->getMultimediaObject();
        if ($multimediaObject->isPrototype()) {
            return;
        }

        $multimediaObject->setPropertyAsDateTime(
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(),
            $this->newExpirationDate()
        );
        $multimediaObject->setProperty(
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewExpirationDateKey(),
            [$this->expiredVideoConfigurationService->getExpirationDateDaysConf()]
        );

        $this->save($multimediaObject);
    }

    public function onMultimediaObjectClone(MultimediaObjectCloneEvent $event): void
    {
        $multimediaObjects = $event->getMultimediaObjects();
        $origin = $multimediaObjects['origin'];
        $cloned = $multimediaObjects['clon'];
        if ($origin->isPrototype() || $cloned->isPrototype()) {
            return;
        }

        foreach ([
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(),
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewExpirationDateKey(),
        ] as $key) {
            $cloned->setProperty($key, $origin->getProperty($key));
        }

        $this->save($cloned);
    }

    private function newExpirationDate(): \DateTime
    {
        if ($this->authorizationChecker->isGranted($this->expiredVideoConfigurationService->getUnlimitedDateExpiredVideoCodePermission())) {
            return new \DateTime()->setDate(9999, 1, 1);
        }

        return new \DateTime('+'.$this->expiredVideoConfigurationService->getExpirationDateDaysConf().' days');
    }

    private function save(MultimediaObject $multimediaObject): void
    {
        $this->documentManager->persist($multimediaObject);
        $this->documentManager->flush();
    }
}
