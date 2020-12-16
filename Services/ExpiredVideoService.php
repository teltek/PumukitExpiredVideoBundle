<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ExpiredVideoService
{
    private $dm;
    private $authorizationChecker;
    private $expiredVideoConfigurationService;
    private $mmobjRepo;

    public function __construct(
        DocumentManager $documentManager,
        AuthorizationCheckerInterface $authorizationChecker,
        ExpiredVideoConfigurationService $expiredVideoConfigurationService
    ) {
        $this->dm = $documentManager;
        $this->authorizationChecker = $authorizationChecker;
        $this->expiredVideoConfigurationService = $expiredVideoConfigurationService;
        $this->mmobjRepo = $this->dm->getRepository(MultimediaObject::class);
    }

    public function getExpiredVideos()
    {
        $now = new \DateTime();

        return $this->mmobjRepo->createQueryBuilder()
            ->field($this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(true))->exists(true)
            ->field($this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(true))->lte($now->format('c'))
            ->getQuery()
            ->execute()
        ;
    }

    public function getExpiredVideosByDateAndRange(int $days, bool $range = true)
    {
        $qb = $this->mmobjRepo->createQueryBuilder()
            ->field($this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(true))->exists(true);

        if ($range) {
            $now = new \DateTimeImmutable(date('Y-m-d H:i:s'));
            $date = $now->add(new \DateInterval('P'.$days.'D'));
            $qb->field($this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(true))->equals(['$gte' => $now->format('c'), '$lte' => $date->format('c')]);
        } else {
            $today = new \DateTimeImmutable(date('Y-m-d'));
            $from = $today->add(new \DateInterval('P'.$days.'D'));
            $to = $today->add(new \DateInterval('P'.($days + 1).'D'));
            $qb->field($this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(true))->equals(['$gte' => $from->format('c'), '$lt' => $to->format('c')]);
        }

        return $qb->getQuery()->execute();
    }

    public function renewMultimediaObject(MultimediaObject $multimediaObject, \DateTime $date): void
    {
        if ($this->authorizationChecker->isGranted('renew', $multimediaObject) && !$multimediaObject->isPrototype()) {
            $this->updateMultimediaObjectExpirationDate(
                $multimediaObject,
                $this->expiredVideoConfigurationService->getExpirationDateDaysConf(),
                $date
            );
        }
    }

    public function getExpirationDateByPermission(): ?\DateTime
    {
        $newRenovationDate = null;
        if ($this->authorizationChecker->isGranted($this->expiredVideoConfigurationService->getUnlimitedDateExpiredVideoCodePermission())) {
            $date = new \DateTime();
            $date->setDate(9999, 01, 01);
            $newRenovationDate = $date;
        } else {
            $newRenovationDate = new \DateTime('+'.$this->expiredVideoConfigurationService->getUnlimitedExpirationDateDays().' days');
        }

        return $newRenovationDate;
    }

    private function updateMultimediaObjectExpirationDate(MultimediaObject $multimediaObject, int $days, \DateTime $newRenovationDate): void
    {
        $multimediaObject->setPropertyAsDateTime(
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(),
            $newRenovationDate
        );
        $renewedExpirationDates = $multimediaObject->getProperty(
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewExpirationDateKey()
        );
        $renewedExpirationDates[] = $days;
        $multimediaObject->setProperty(
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewExpirationDateKey(),
            $renewedExpirationDates
        );

        $this->dm->persist($multimediaObject);
        $this->dm->flush();
    }
}
