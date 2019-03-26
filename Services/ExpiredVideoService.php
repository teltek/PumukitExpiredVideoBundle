<?php

namespace Pumukit\ExpiredVideoBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Pumukit\NotificationBundle\Services\SenderService;
use Psr\Log\LoggerInterface;

/**
 * Class ExpiredVideoService.
 */
class ExpiredVideoService
{
    private $dm;
    private $authorizationChecker;
    private $router;
    private $senderService;
    private $translator;
    private $logger;
    private $mmobjRepo;
    private $personRepo;
    private $template = 'PumukitExpiredVideoBundle:Email:notification.html.twig';
    private $videos = 'videos';
    private $days;
    private $subject = array(
        'removeOwner' => 'PuMuKIT - Remove owner of the following video.',
        'expired' => 'PuMuKIT - These videos will be expired coming soon.',
    );

    /**
     * ExpiredVideoService constructor.
     *
     * @param DocumentManager               $documentManager
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param Router                        $router
     * @param LoggerInterface               $logger
     * @param SenderService                 $senderService
     * @param TranslatorInterface           $translator
     * @param array|null                    $subject
     * @param int                           $days
     */
    public function __construct(DocumentManager $documentManager, AuthorizationCheckerInterface $authorizationChecker, Router $router, LoggerInterface $logger, SenderService $senderService, TranslatorInterface $translator, array $subject = null, $days = 365)
    {
        $this->dm = $documentManager;
        $this->authorizationChecker = $authorizationChecker;
        $this->router = $router;
        $this->senderService = $senderService;
        $this->translator = $translator;
        $this->logger = $logger;
        $this->mmobjRepo = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
        $this->personRepo = $this->dm->getRepository('PumukitSchemaBundle:Person');

        if ($subject) {
            $this->subject = $subject;
        }

        $this->days = $days;
    }

    /**
     * @param $aEmails
     * @param $sType
     *
     * @return bool|string
     */
    public function generateNotification($aEmails, $sType)
    {
        $output = '';

        if ($this->senderService && $this->senderService->isEnabled()) {
            $parameters = array(
                'subject' => $this->subject[$sType],
                'type' => $sType,
                'sender_name' => $this->senderService->getSenderName(),
            );

            foreach ($aEmails as $sUserId => $aData) {
                $aUserKeys = $this->addRenewUniqueKeys($sUserId, $aData);
                $parameters['data'] = $aUserKeys;

                $output = $this->senderService->sendNotification(
                    $aData['email'],
                    $this->translator->trans($this->subject[$sType]),
                    $this->template,
                    $parameters,
                    false
                );

                if (0 < $output) {
                    $infoLog = __CLASS__.' ['.__FUNCTION__.'] Sent notification email to "'.$aData['email'].'"';
                    $this->logger->info($infoLog);
                } else {
                    $infoLog = __CLASS__.' ['.__FUNCTION__.'] Unable to send notification email to "'.$aData['email'].'", '.$output.'email(s) were sent.';
                    $this->logger->info($infoLog);
                }
            }
        }

        return $output;
    }

    /**
     * @param $sUserId
     * @param $aData
     *
     * @return array
     */
    private function addRenewUniqueKeys($sUserId, $aData)
    {
        $aUserKeys = array();

        $sTokenUser = $this->generateExpiredToken();

        $aUserKeys['all'] = $sTokenUser;
        $person = $this->personRepo->findOneBy(array('_id' => new \MongoId($sUserId)));
        $person->setProperty('expiration_key', $sTokenUser);

        foreach ($aData[$this->videos] as $sObjectId) {
            $sTokenMO = $this->generateExpiredToken();

            $mmObj = $this->mmobjRepo->findOneBy(array('_id' => new \MongoId($sObjectId)));
            $mmObj->setProperty('expiration_key', $sTokenMO);

            $aUserKeys['videos'][$mmObj->getId()]['token'] = $sTokenMO;
            $aUserKeys['videos'][$mmObj->getId()]['title'] = $mmObj->getTitle();
            $aUserKeys['videos'][$mmObj->getId()]['obj'] = $mmObj;
            $aUserKeys['videos'][$mmObj->getId()]['expired'] = $mmObj->getPropertyAsDateTime('expiration_date');

            $this->dm->flush();
        }

        return $aUserKeys;
    }

    /**
     * @return \MongoId
     */
    public function generateExpiredToken()
    {
        return new \MongoId();
    }

    /**
     * @return mixed
     *
     * @throws \Exception
     */
    public function getExpiredVideos()
    {
        $now = new \DateTime();

        $expiredVideos = $this->mmobjRepo->createQueryBuilder()
            ->field('properties.expiration_date')->exists(true)
            ->field('properties.expiration_date')->lte($now->format('c'))
            ->getQuery()
            ->execute();

        return $expiredVideos;
    }

    /**
     * @param $days
     *
     * @return mixed
     *
     * @throws \Doctrine\ODM\MongoDB\MongoDBException
     */
    public function getExpiredVideosToDelete($days)
    {
        $now = new \DateTime();
        $now->sub(new \DateInterval('P'.$days.'D'));

        $qb = $this->mmobjRepo->createQueryBuilder();
        $qb->field('properties.expiration_date')->exists(true);
        $qb->field('properties.expiration_date')->lte($now->format('c'));

        $expiredVideos = $qb->getQuery()->execute();

        return $expiredVideos;
    }

    /**
     * @param $days
     * @param $range
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function getExpiredVideosByDateAndRange($days, $range = true)
    {
        $qb = $this->mmobjRepo->createQueryBuilder()->field('properties.expiration_date')->exists(true);

        if ($range) {
            $now = new \DateTimeImmutable(date('Y-m-d H:i:s'));
            $date = $now->add(new \DateInterval('P'.$days.'D'));
            $qb->field('properties.expiration_date')->equals(array('$gte' => $now->format('c'), '$lte' => $date->format('c')));
        } else {
            $today = new \DateTimeImmutable(date('Y-m-d'));
            $from = $today->add(new \DateInterval('P'.($days).'D'));
            $to = $today->add(new \DateInterval('P'.($days + 1).'D'));
            $qb->field('properties.expiration_date')->equals(array('$gte' => $from->format('c'), '$lt' => $to->format('c')));
        }

        $expiredVideos = $qb->getQuery()->execute();

        return $expiredVideos;
    }

    /**
     * @param MultimediaObject $multimediaObject
     * @param                  $date
     *
     * @throws \Exception
     */
    public function renewMultimediaObject(MultimediaObject $multimediaObject, $date)
    {
        if ($this->authorizationChecker->isGranted('renew', $multimediaObject) && !$multimediaObject->isPrototype()) {
            $this->updateMultimediaObjectExpirationDate($this->dm, $multimediaObject, $this->days, $date);
        }
    }

    /**
     * @param DocumentManager  $dm
     * @param MultimediaObject $multimediaObject
     * @param                  $days
     * @param                  $newRenovationDate
     */
    private function updateMultimediaObjectExpirationDate(DocumentManager $dm, MultimediaObject $multimediaObject, $days, $newRenovationDate)
    {
        $multimediaObject->setPropertyAsDateTime('expiration_date', $newRenovationDate);
        $renewedExpirationDates = $multimediaObject->getProperty('renew_expiration_date');
        $renewedExpirationDates[] = $days;
        $multimediaObject->setProperty('renew_expiration_date', $renewedExpirationDates);
        $dm->persist($multimediaObject);
        $dm->flush();
    }

    /**
     * @return \DateTime|null
     *
     * @throws \Exception
     */
    public function getExpirationDateByPermission()
    {
        $newRenovationDate = null;
        if ($this->authorizationChecker->isGranted('ROLE_UNLIMITED_EXPIRED_VIDEO')) {
            $date = new \DateTime();
            $date->setDate(9999, 01, 01);
            $this->days = 3649635;
            $newRenovationDate = $date;
        } else {
            $newRenovationDate = new \DateTime('+'.$this->days.' days');
        }

        return $newRenovationDate;
    }
}
