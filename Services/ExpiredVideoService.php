<?php

namespace Pumukit\ExpiredVideoBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Translation\TranslatorInterface;
use Pumukit\NotificationBundle\Services\SenderService;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class ExpiredVideoService
{
    private $dm;
    private $router;
    private $senderService;
    private $translator;
    private $logger;
    private $subject = array(
        "removeOwner" => "PuMuKIT2 - Remove owner of the following video.",
        "expired" => "PuMuKIT2 - This video will be expired coming soon.",
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
    }

    public function generateNotification($emails, $type, $mmObj)
    {
        if ($this->senderService && $this->senderService->isEnabled()) {

            $token = $this->addKeyToMMO($mmObj);

            $emailTo = $emails;
            $template = 'PumukitExpiredVideoBundle:Email:notification.html.twig';
            $parameters = array(
                'subject' => $this->subject[$type],
                'type' => $type,
                'sender_name' => $this->senderService->getSenderName(),
                'token' => $token,
                'mmobj' => $mmObj,
            );
            $output = $this->senderService->sendMultipleNotification($emailTo, $this->subject[$type], $template, $parameters, false);

            $sEmails = '';
            foreach($emailTo as $email) {
                $sEmails .= $email . " - ";
            }
            if (0 < $output) {
                $infoLog = __CLASS__.' ['.__FUNCTION__.'] Sent notification email to "'.$sEmails.'"';
                $this->logger->addInfo($infoLog);
            } else {
                $infoLog = __CLASS__.' ['.__FUNCTION__.'] Unable to send notification email to "'.$sEmails.'", '.$output.'email(s) were sent.';
                $this->logger->addInfo($infoLog);
            }
            return $output;
        }
    }

    private function addKeyToMMO($mmObj)
    {
        $token = new \MongoId();

        $mmObj->setProperty('expiration_key', $token);

        $this->dm->flush();

        return $token;
    }

}
