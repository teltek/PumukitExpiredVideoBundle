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
    private $dm;
    private $expiredVideoConfigurationService;
    private $days;
    private $newRenovationDate;
    private $authorizationChecker;

    public function __construct(
        DocumentManager $documentManager,
        ExpiredVideoConfigurationService $expiredVideoConfigurationService,
        AuthorizationCheckerInterface $authorizationChecker
    ) {
        $this->dm = $documentManager;
        $this->expiredVideoConfigurationService = $expiredVideoConfigurationService;
        $this->authorizationChecker = $authorizationChecker;
        $this->days = $this->expiredVideoConfigurationService->getExpirationDateDaysConf();
        $this->getNewRenovationDate();
    }

    public function onMultimediaObjectCreate(MultimediaObjectEvent $event): void
    {
        $properties = [];
        if ($this->expiredVideoConfigurationService->isDeactivatedService()) {
            return;
        }

        $multimediaObject = $event->getMultimediaObject();
        if ($multimediaObject->isPrototype()) {
            return;
        }

        $properties['expiration_date'] = $this->newRenovationDate;
        $properties['renew_expiration_date'] = [$this->expiredVideoConfigurationService->getExpirationDateDaysConf()];
        $this->updateProperties($multimediaObject, $properties);
    }

    public function onMultimediaObjectClone(MultimediaObjectCloneEvent $event): void
    {
        $properties = [];
        if ($this->expiredVideoConfigurationService->isDeactivatedService()) {
            return;
        }

        $multimediaObjects = $event->getMultimediaObjects();
        $validObjects = $this->checkValidMultimediaObject($multimediaObjects['origin'], $multimediaObjects['clon']);
        if (!$validObjects) {
            return;
        }

        $this->updateMultimediaObject($multimediaObjects['origin'], $multimediaObjects['clon']);
        $properties['expiration_date'] = $multimediaObjects['origin']->getProperty(
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey()
        );
        $properties['renew_expiration_date'] = $multimediaObjects['origin']->getProperty(
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewExpirationDateKey()
        );
        $this->updateProperties($multimediaObjects['clon'], $properties, false);
    }

    private function getNewRenovationDate(): void
    {
        try {
            if ($this->authorizationChecker->isGranted($this->expiredVideoConfigurationService->getUnlimitedDateExpiredVideoCodePermission())) {
                $date = new \DateTime();
                $date->setDate(9999, 01, 01);
                $this->days = $this->expiredVideoConfigurationService->getUnlimitedExpirationDateDays();
                $this->newRenovationDate = $date;
            } else {
                $this->newRenovationDate = new \DateTime('+'.$this->days.' days');
            }
        } catch (\Exception $exception) {
            $this->newRenovationDate = new \DateTime('+'.$this->days.' days');
        }
    }

    private function checkValidMultimediaObject(MultimediaObject $origin, MultimediaObject $cloned): bool
    {
        return !($origin->isPrototype() || $cloned->isPrototype());
    }

    private function updateMultimediaObject(MultimediaObject $origin, MultimediaObject $cloned): void
    {
        $properties = $origin->getProperties();
        if (is_array($properties)) {
            $this->updateProperties($cloned, $properties, false);
        }
    }

    private function updateProperties(MultimediaObject $multimediaObject, array $properties, bool $format = true): void
    {
        if ($format) {
            $multimediaObject->setPropertyAsDateTime(
                $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(),
                $properties['expiration_date']
            );
        } else {
            $multimediaObject->setProperty(
                $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(),
                $properties['expiration_date']
            );
        }

        $multimediaObject->setProperty(
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewExpirationDateKey(),
            $properties['renew_expiration_date']
        );

        $this->dm->persist($multimediaObject);
        $this->dm->flush();
    }
}
