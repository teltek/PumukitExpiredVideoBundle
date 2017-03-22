<?php

namespace Pumukit\ExpiredVideoBundle\EventListener;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Event\MultimediaObjectEvent;
use Pumukit\SchemaBundle\Event\MultimediaObjectCloneEvent;

class InitDateListener
{
    private $dm;
    private $inteval;

    public function __construct(DocumentManager $documentManager, $inteval = 365)
    {
        $this->dm = $documentManager;
        $this->interval = (int) $inteval;

        //TODO Move to configuration.
        new \DateTime('+'.$this->interval.' days');
    }

    public function onMultimediaobjectCreate(MultimediaObjectEvent $event)
    {
        $mm = $event->getMultimediaObject();

        if ($mm->isPrototype()) {
            return;
        }

        $date = new \DateTime('+'.$this->interval.' days');
        $mm->setPropertyAsDateTime('expiration_date', $date);
        $mm->setProperty('renew_expiration_date', $this->interval);

        $this->dm->persist($mm);
        $this->dm->flush();
    }

    public function onMultimediaobjectClone(MultimediaObjectCloneEvent $event)
    {
        $aMultimediaObjects = $event->getMultimediaObjects();

        if ($aMultimediaObjects['origin']->isPrototype() or $aMultimediaObjects['clon']->isPrototype()) {
            return;
        }

        $aOriginProperties = $aMultimediaObjects['origin']->getProperties();

        $aMultimediaObjects['clon']->setProperty('expiration_date', $aOriginProperties['expiration_date']);
        $aMultimediaObjects['clon']->setProperty('renew_expiration_date', $aOriginProperties['renew_expiration_date']);
        $this->dm->persist($aMultimediaObjects['clon']);
        $this->dm->flush();
    }
}
