<?php

namespace Pumukit\ExpiredVideoBundle\EventListener;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Event\MultimediaObjectEvent;

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
        $mm->setProperty('expiration_date', $date->format('c'));
        $mm->setProperty('renew_expiration_date', $this->interval);

        $this->dm->persist($mm);
        $this->dm->flush();
    }
}
