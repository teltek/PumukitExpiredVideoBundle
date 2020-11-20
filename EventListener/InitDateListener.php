<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\EventListener;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Event\MultimediaObjectCloneEvent;
use Pumukit\SchemaBundle\Event\MultimediaObjectEvent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class InitDateListener
{
    private $dm;
    private $days;
    private $newRenovationDate;
    private $authorizationChecker;

    public function __construct(DocumentManager $documentManager, AuthorizationCheckerInterface $authorizationChecker, int $days = 365)
    {
        $this->dm = $documentManager;
        $this->days = $days;
        $this->authorizationChecker = $authorizationChecker;
        $this->getNewRenovationDate();
    }

    public function onMultimediaObjectCreate(MultimediaObjectEvent $event): void
    {
        if (!$this->checkConfiguration()) {
            return;
        }

        $multimediaObject = $event->getMultimediaObject();
        if ($multimediaObject->isPrototype()) {
            return;
        }

        $properties['expiration_date'] = $this->newRenovationDate;
        $properties['renew_expiration_date'] = [$this->days];
        $this->updateProperties($this->dm, $multimediaObject, $properties);
    }

    public function onMultimediaObjectClone(MultimediaObjectCloneEvent $event): void
    {
        if ($this->checkConfiguration()) {
            return;
        }

        $multimediaObjects = $event->getMultimediaObjects();
        $validObjects = $this->checkValidMultimediaObject($multimediaObjects['origin'], $multimediaObjects['clon']);
        if (!$validObjects) {
            return;
        }

        $this->updateMultimediaObject($this->dm, $multimediaObjects['origin'], $multimediaObjects['clon']);
        $properties['expiration_date'] = $multimediaObjects['origin']->getProperty('expiration_date');
        $properties['renew_expiration_date'] = $multimediaObjects['origin']->getProperty('renew_expiration_date');
        $this->updateProperties($this->dm, $multimediaObjects['clon'], $properties, false);
    }

    private function getNewRenovationDate(): void
    {
        try {
            if ($this->authorizationChecker->isGranted('ROLE_UNLIMITED_EXPIRED_VIDEO')) {
                $date = new \DateTime();
                $date->setDate(9999, 01, 01);
                $this->days = 3649635;
                $this->newRenovationDate = $date;
            } else {
                $this->newRenovationDate = new \DateTime('+'.$this->days.' days');
            }
        } catch (\Exception $exception) {
            $this->newRenovationDate = new \DateTime('+'.$this->days.' days');
        }
    }

    private function checkConfiguration(): bool
    {
        return !(0 === $this->days);
    }

    private function checkValidMultimediaObject(MultimediaObject $origin, MultimediaObject $cloned): bool
    {
        return !($origin->isPrototype() || $cloned->isPrototype());
    }

    private function updateMultimediaObject(DocumentManager $dm, MultimediaObject $origin, MultimediaObject $cloned): void
    {
        $properties = $origin->getProperties();
        if (is_array($properties)) {
            $this->updateProperties($dm, $cloned, $properties, false);
        }
    }

    private function updateProperties(DocumentManager $dm, MultimediaObject $multimediaObject, array $properties, bool $format = true): void
    {
        if ($format) {
            $multimediaObject->setPropertyAsDateTime('expiration_date', $properties['expiration_date']);
        } else {
            $multimediaObject->setProperty('expiration_date', $properties['expiration_date']);
        }
        $multimediaObject->setProperty('renew_expiration_date', $properties['renew_expiration_date']);

        $dm->persist($multimediaObject);
        $dm->flush();
    }
}
