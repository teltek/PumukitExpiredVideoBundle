<?php

namespace Pumukit\ExpiredVideoBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Translation\TranslatorInterface;
use Pumukit\NotificationBundle\Services\SenderService;

class ExpiredVideoService
{
    private $dm;
    private $router;
    private $senderService;
    private $translator;

    public function __construct(DocumentManager $documentManager, Router $router, SenderService $senderService = null, TranslatorInterface $translator)
    {
        $this->dm = $documentManager;
        $this->router = $router;
        $this->senderService = $senderService;
        $this->translator = $translator;
    }

    public function generateNotification($emails)
    {
        $token = $this->generateToken();

        if ($this->senderService && $this->senderService->isEnabled()) {

            $subject = 'Notification expired video';
            $emailTo = $emails;
            $body = 'test';
            $template = 'PumukitNotificationBundle:Email:notification.html.twig';
            $parameters = array(
                'subject' => $subject,
                'body' => $body,
                'sender_name' => $this->senderService->getSenderName(),
            );
            $output = $this->senderService->sendNotification($emailTo, $subject, $template, $parameters, false);
            /*if (0 < $output) {
                $infoLog = __CLASS__.' ['.__FUNCTION__.'] Sent notification email to "'.$emailTo.'"';
                $this->logger->addInfo($infoLog);
            } else {
                $infoLog = __CLASS__.' ['.__FUNCTION__.'] Unable to send notification email to "'.$emailTo.'", '.$output.'email(s) were sent.';
                $this->logger->addInfo($infoLog);
            }*/

            return $output;
        }
    }

    public function generateToken()
    {
        return new \MongoId();
    }
}
