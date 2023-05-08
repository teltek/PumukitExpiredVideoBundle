<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\User;
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

    public function renewedVideosWithoutOwner(?string $username): array
    {
        $now = new \DateTime();
        $criteria = [
            'status' => ['$ne' => MultimediaObject::STATUS_PROTOTYPE],
            'type' => ['$ne' => MultimediaObject::TYPE_LIVE],
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(true) => ['$gt' => $now->format('c')],
        ];

        $renewVideos = $this->videosByCriteria($criteria);

        $multimediaObjects = [];
        foreach ($renewVideos as $multimediaObject) {
            if ($multimediaObject instanceof MultimediaObject && !$multimediaObject->getPeopleByRoleCod('owner', true)) {
                $multimediaObjects[] = $multimediaObject;
            }
        }

        $person = null;
        if ($username) {
            $user = $this->dm->getRepository(User::class)->findOneBy(['username' => $username]);
            if (!$user) {
                throw new \Exception('User not found '.$user);
            }
            $person = $user->getPerson();
            if (!$person) {
                throw new \Exception('User havent got person associated');
            }
        }

        $expiredOwnerRole = $this->dm->getRepository(Role::class)->findOneBy([
            'cod' => ExpiredVideoConfigurationService::EXPIRED_OWNER_CODE,
        ]);

        if (!$expiredOwnerRole instanceof Role) {
            throw new \Exception('Role not found');
        }

        $filteredMultimediaObjects = [];
        foreach ($multimediaObjects as $multimediaObject) {
            if ($person && $multimediaObject->containsPersonWithRole($person, $expiredOwnerRole)) {
                $filteredMultimediaObjects[] = $multimediaObject;
            }

            if (!$person && !$multimediaObject->getPeopleByRoleCod($expiredOwnerRole)) {
                $filteredMultimediaObjects[] = $multimediaObject;
            }
        }

        return $filteredMultimediaObjects;
    }

    private function videosByCriteria(array $criteria): array
    {
        return $this->dm->getRepository(MultimediaObject::class)->findBy($criteria);
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
