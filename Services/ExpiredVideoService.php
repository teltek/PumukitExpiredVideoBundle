<?php

namespace Pumukit\ExpiredVideoBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Translation\TranslatorInterface;
use Pumukit\NotificationBundle\Services\SenderService;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Pumukit\SchemaBundle\Document\Person;
use Pumukit\SchemaBundle\Document\MultimediaObject;

class ExpiredVideoService
{
    private $dm;
    private $router;
    private $senderService;
    private $translator;
    private $logger;
    private $videos = "videos";
    private $subject = array(
        "removeOwner" => "PuMuKIT2 - Remove owner of the following video.",
        "expired" => "PuMuKIT2 - These videos will be expired coming soon.",
    );

    public function __construct(
        DocumentManager $documentManager,
        Router $router,
        LoggerInterface $logger,
        SenderService $senderService = null,
        TranslatorInterface $translator
    ) {
        $this->dm = $documentManager;
        $this->router = $router;
        $this->senderService = $senderService;
        $this->translator = $translator;
        $this->logger = $logger;
        $this->mmobjRepo = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
        $this->personRepo = $this->dm->getRepository('PumukitSchemaBundle:Person');
    }

    public function generateNotification($aEmails, $sType)
    {
        if ($this->senderService && $this->senderService->isEnabled()) {

            $template = 'PumukitExpiredVideoBundle:Email:notification.html.twig';
            $parameters = array(
                'subject' => $this->subject[$sType],
                'type' => $sType,
                'sender_name' => $this->senderService->getSenderName()
            );

            foreach ($aEmails as $sUserId => $aData) {

                $aUserKeys = $this->addRenewUniqueKeys($sUserId, $aData);
                $parameters['data'] = $aUserKeys;

                $output = $this->senderService->sendNotification(
                    $aData['email'],
                    $this->translator->trans($this->subject[$sType]),
                    $template,
                    $parameters,
                    false
                );

                if (0 < $output) {
                    $infoLog = __CLASS__ .' [' . __FUNCTION__ . '] Sent notification email to "' . $aData['email'] . '"';
                    $this->logger->addInfo($infoLog);
                } else {
                    $infoLog = __CLASS__ . ' [' . __FUNCTION__ . '] Unable to send notification email to "' . $aData['email'] . '", ' . $output . 'email(s) were sent.';
                    $this->logger->addInfo($infoLog);
                }
            }

            return $output;
        }
    }

    /**
     * @param $sUserId
     * @param $aData
     * @return array
     */
    private function addRenewUniqueKeys($sUserId, $aData)
    {
        $aUserKeys = array();

        $sTokenUser = new \MongoId();
        $aUserKeys["all"] = $sTokenUser;
        $person = $this->personRepo->findOneBy(array('_id' => new \MongoId($sUserId)));

        $person->setProperty('expiration_key', $sTokenUser);

        foreach ($aData[$this->videos] as $sObjectId) {

            $sTokenMO = new \MongoId();

            $mmObj = $this->mmobjRepo->findOneBy(array('_id' => new \MongoId($sObjectId)));
            $mmObj->setProperty('expiration_key', $sTokenMO);

            $aUserKeys["videos"][$mmObj->getId()]['token'] = $sTokenMO;
            $aUserKeys["videos"][$mmObj->getId()]['title'] = $mmObj->getTitle();
            $aUserKeys["videos"][$mmObj->getId()]['expired'] = $mmObj->getPropertyAsDateTime('expiration_date');

            $this->dm->flush();
        }

        return $aUserKeys;
    }
}
