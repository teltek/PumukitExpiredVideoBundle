<?php

namespace Pumukit\ExpiredVideoBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;
use Pumukit\NotificationBundle\Services\SenderService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Person;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Translation\TranslatorInterface;

class ExpiredVideoService
{
    private $dm;
    private $authorizationChecker;
    private $senderService;
    private $translator;
    private $logger;
    private $mmobjRepo;
    private $personRepo;
    private $template = 'PumukitExpiredVideoBundle:Email:notification.html.twig';
    private $videos = 'videos';
    private $days;
    private $subject = [
        'removeOwner' => 'PuMuKIT - Remove owner of the following video.',
        'expired' => 'PuMuKIT - These videos will be expired coming soon.',
    ];

    public function __construct(DocumentManager $documentManager, AuthorizationCheckerInterface $authorizationChecker, LoggerInterface $logger, SenderService $senderService, TranslatorInterface $translator, array $subject = null, $days = 365)
    {
        $this->dm = $documentManager;
        $this->authorizationChecker = $authorizationChecker;
        $this->senderService = $senderService;
        $this->translator = $translator;
        $this->logger = $logger;
        $this->mmobjRepo = $this->dm->getRepository(MultimediaObject::class);
        $this->personRepo = $this->dm->getRepository(Person::class);

        if ($subject) {
            $this->subject = $subject;
        }

        $this->days = $days;
    }

    public function generateNotification(iterable $aEmails, string $sType)
    {
        $output = '';

        if (!$this->senderService->isEnabled()) {
            return $output;
        }

        $parameters = [
            'subject' => $this->subject[$sType],
            'type' => $sType,
            'sender_name' => $this->senderService->getSenderName(),
        ];

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

        return $output;
    }

    public function generateExpiredToken(): \MongoId
    {
        return new \MongoId();
    }

    public function getExpiredVideos()
    {
        $now = new \DateTime();

        return $this->mmobjRepo->createQueryBuilder()
            ->field('properties.expiration_date')->exists(true)
            ->field('properties.expiration_date')->lte($now->format('c'))
            ->getQuery()
            ->execute()
        ;
    }

    public function getExpiredVideosToDelete(int $days)
    {
        $now = new \DateTime();
        $now->sub(new \DateInterval('P'.$days.'D'));

        $qb = $this->mmobjRepo->createQueryBuilder();
        $qb->field('properties.expiration_date')->exists(true);
        $qb->field('properties.expiration_date')->lte($now->format('c'));

        return $qb->getQuery()->execute();
    }

    public function getExpiredVideosByDateAndRange(int $days, bool $range = true)
    {
        $qb = $this->mmobjRepo->createQueryBuilder()->field('properties.expiration_date')->exists(true);

        if ($range) {
            $now = new \DateTimeImmutable(date('Y-m-d H:i:s'));
            $date = $now->add(new \DateInterval('P'.$days.'D'));
            $qb->field('properties.expiration_date')->equals(['$gte' => $now->format('c'), '$lte' => $date->format('c')]);
        } else {
            $today = new \DateTimeImmutable(date('Y-m-d'));
            $from = $today->add(new \DateInterval('P'.$days.'D'));
            $to = $today->add(new \DateInterval('P'.($days + 1).'D'));
            $qb->field('properties.expiration_date')->equals(['$gte' => $from->format('c'), '$lt' => $to->format('c')]);
        }

        return $qb->getQuery()->execute();
    }

    public function renewMultimediaObject(MultimediaObject $multimediaObject, \DateTime $date): void
    {
        if ($this->authorizationChecker->isGranted('renew', $multimediaObject) && !$multimediaObject->isPrototype()) {
            $this->updateMultimediaObjectExpirationDate($this->dm, $multimediaObject, $this->days, $date);
        }
    }

    public function getExpirationDateByPermission(): ?\DateTime
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

    private function addRenewUniqueKeys(string $sUserId, array $aData): array
    {
        $aUserKeys = [];

        $sTokenUser = $this->generateExpiredToken();

        $aUserKeys['all'] = $sTokenUser;
        $person = $this->personRepo->findOneBy(['_id' => new \MongoId($sUserId)]);
        $person->setProperty('expiration_key', $sTokenUser);

        foreach ($aData[$this->videos] as $sObjectId) {
            $sTokenMO = $this->generateExpiredToken();

            $mmObj = $this->mmobjRepo->findOneBy(['_id' => new \MongoId($sObjectId)]);
            $mmObj->setProperty('expiration_key', $sTokenMO);

            $aUserKeys['videos'][$mmObj->getId()]['token'] = $sTokenMO;
            $aUserKeys['videos'][$mmObj->getId()]['title'] = $mmObj->getTitle();
            $aUserKeys['videos'][$mmObj->getId()]['obj'] = $mmObj;
            $aUserKeys['videos'][$mmObj->getId()]['expired'] = $mmObj->getPropertyAsDateTime('expiration_date');
        }

        $this->dm->flush();

        return $aUserKeys;
    }

    private function updateMultimediaObjectExpirationDate(DocumentManager $dm, MultimediaObject $multimediaObject, int $days, \DateTime $newRenovationDate): void
    {
        $multimediaObject->setPropertyAsDateTime('expiration_date', $newRenovationDate);
        $renewedExpirationDates = $multimediaObject->getProperty('renew_expiration_date');
        $renewedExpirationDates[] = $days;
        $multimediaObject->setProperty('renew_expiration_date', $renewedExpirationDates);
        $dm->persist($multimediaObject);
        $dm->flush();
    }
}
