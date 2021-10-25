<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Person;
use Pumukit\SchemaBundle\Document\PersonInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ExpiredVideoRenewService
{
    private $documentManager;
    private $expiredVideoConfigurationService;
    private $authorizationChecker;
    private $personalScopeRoleCode;

    public function __construct(
        DocumentManager $documentManager,
        ExpiredVideoConfigurationService $expiredVideoConfigurationService,
        AuthorizationCheckerInterface $authorizationChecker,
        string $personalScopeRoleCode
    ) {
        $this->documentManager = $documentManager;
        $this->expiredVideoConfigurationService = $expiredVideoConfigurationService;
        $this->authorizationChecker = $authorizationChecker;
        $this->personalScopeRoleCode = $personalScopeRoleCode;
    }

    public function findVideoByRenewKey(string $key)
    {
        $renewKeyProperty = $this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewKey(true);

        return $this->documentManager->getRepository(MultimediaObject::class)->findOneBy([
            $renewKeyProperty => new ObjectId($key),
        ]);
    }

    public function getPersonWithRenewKey(string $key)
    {
        return $this->documentManager->getRepository(Person::class)->findOneBy([
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewKey(true) => new ObjectId($key),
        ]);
    }

    public function isOwner(MultimediaObject $multimediaObject, UserInterface $user): bool
    {
        if ($this->authorizationChecker->isGranted(ExpiredVideoConfigurationService::ROLE_ACCESS_EXPIRED_VIDEO)) {
            return true;
        }

        $people = $multimediaObject->getPeopleByRoleCod($this->personalScopeRoleCode, true);

        if (empty($people)) {
            return false;
        }

        foreach ($people as $person) {
            if ($person->getEmail() === $user->getEmail()) {
                return true;
            }
        }

        return false;
    }

    public function renew(MultimediaObject $multimediaObject): void
    {
        $days = $this->expiredVideoConfigurationService->getExpirationDateDaysConf();

        $aRenew = $multimediaObject->getProperty(
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewExpirationDateKey()
        );

        $aRenew[] = $days;
        $multimediaObject->setProperty(
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewExpirationDateKey(),
            $aRenew
        );

        $multimediaObject->removeProperty($this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewKey());

        $date = $this->generateRenewDate($days);
        $multimediaObject->setPropertyAsDateTime(
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(),
            $date
        );

        $this->documentManager->flush();
    }

    public function findMultimediaObjectsByPerson(PersonInterface $person)
    {
        return $this->documentManager->getRepository(MultimediaObject::class)->findBy(
            [
                'people.people._id' => $person->getId(),
                $this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewKey(true) => [
                    '$exists' => true,
                ],
            ]
        );
    }

    public function renewAllMultimediaObjects($multimediaObjects, UserInterface $user, PersonInterface $person): void
    {
        foreach ($multimediaObjects as $multimediaObject) {
            if (!$this->isOwner($multimediaObject, $user)) {
                continue;
            }

            $this->renew($multimediaObject);
        }

        $this->removePersonRenewProperty($person);
    }

    private function generateRenewDate(int $days): \DateTimeInterface
    {
        $date = new \DateTime();

        return $date->add(new \DateInterval('P'.$days.'D'));
    }

    private function removePersonRenewProperty(PersonInterface $person)
    {
        $person->removeProperty(
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey()
        );

        $this->documentManager->flush();
    }
}
