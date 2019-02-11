<?php

namespace Pumukit\ExpiredVideoBundle\EventListener;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Event\MultimediaObjectEvent;
use Pumukit\SchemaBundle\Event\MultimediaObjectCloneEvent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Class InitDateListener.
 */
class InitDateListener
{
    private $dm;
    private $days;
    private $authorizationChecker;
    private $newRenovationDate;

    /**
     * InitDateListener constructor.
     *
     * @param DocumentManager               $documentManager
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param int                           $days
     *
     * @throws \Exception
     */
    public function __construct(DocumentManager $documentManager, AuthorizationCheckerInterface $authorizationChecker, $days = 365)
    {
        $this->dm = $documentManager;
        $this->days = (int) $days;
        $this->authorizationChecker = $authorizationChecker;

        if ($authorizationChecker->isGranted('ROLE_UNLIMITED_EXPIRED_VIDEO')) {
            $date = new \DateTime();
            $date->setDate(9999, 01, 01);
            $this->days = 3649635;
            $this->newRenovationDate = $date;
        } else {
            $this->newRenovationDate = new \DateTime('+'.$this->days.' days');
        }
    }

    /**
     * @param MultimediaObjectEvent $event
     *
     * @throws \Exception
     */
    public function onMultimediaObjectCreate(MultimediaObjectEvent $event)
    {
        if ($this->checkConfiguration()) {
            $multimediaObject = $event->getMultimediaObject();
            if (!$multimediaObject->isPrototype()) {
                $properties['expiration_date'] = $this->newRenovationDate;
                $properties['renew_expiration_date'] = array($this->days);
                $this->updateProperties($this->dm, $multimediaObject, $properties, true);
            }
        }
    }

    /**
     * @param MultimediaObjectCloneEvent $event
     *
     * @throws \Exception
     */
    public function onMultimediaObjectClone(MultimediaObjectCloneEvent $event)
    {
        if ($this->checkConfiguration()) {
            $multimediaObjects = $event->getMultimediaObjects();
            $validObjects = $this->checkValidMultimediaObject($multimediaObjects['origin'], $multimediaObjects['clon']);
            if ($validObjects) {
                $this->updateMultimediaObject($this->dm, $multimediaObjects['origin'], $multimediaObjects['clon']);
                $properties['expiration_date'] = $multimediaObjects['origin']->getProperty('expiration_date');
                $properties['renew_expiration_date'] = $multimediaObjects['origin']->getProperty('renew_expiration_date');
                $this->updateProperties($this->dm, $multimediaObjects['clon'], $properties, false);
            }
        }
    }

    /**
     * @return bool
     */
    private function checkConfiguration()
    {
        if (0 === $this->days) {
            return false;
        }

        return true;
    }

    /**
     * @param MultimediaObject $origin
     * @param MultimediaObject $cloned
     *
     * @return bool
     */
    private function checkValidMultimediaObject(MultimediaObject $origin, MultimediaObject $cloned)
    {
        if ($origin->isPrototype() || $cloned->isPrototype()) {
            return false;
        }

        return true;
    }

    /**
     * @param DocumentManager  $dm
     * @param MultimediaObject $origin
     * @param MultimediaObject $cloned
     *
     * @throws \Exception
     */
    private function updateMultimediaObject(DocumentManager $dm, MultimediaObject $origin, MultimediaObject $cloned)
    {
        $properties = $origin->getProperties();
        if (is_array($properties)) {
            $this->updateProperties($dm, $cloned, $properties);
        }
    }

    /**
     * @param DocumentManager  $dm
     * @param MultimediaObject $multimediaObject
     * @param array            $properties
     * @param bool             $format
     *
     * @throws \Exception
     */
    private function updateProperties(DocumentManager $dm, MultimediaObject $multimediaObject, array $properties, $format = true)
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
