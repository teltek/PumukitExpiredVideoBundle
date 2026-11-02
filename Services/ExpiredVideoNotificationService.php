<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;
use Pumukit\ExpiredVideoBundle\Utils\TokenUtils;
use Pumukit\NotificationBundle\Services\SenderService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Person;
use Pumukit\SchemaBundle\Document\PersonInterface;
use Pumukit\SchemaBundle\Services\PersonService;
use Symfony\Contracts\Translation\TranslatorInterface;

class ExpiredVideoNotificationService
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly ExpiredVideoConfigurationService $expiredVideoConfigurationService,
        private readonly PersonService $personService,
        private readonly SenderService $senderService,
        private readonly TranslatorInterface $translator,
    ) {}

    public function checkAndSendNotifications(iterable $multimediaObjects): array
    {
        $peopleToNotifyOnMultimediaObject = [];
        foreach ($multimediaObjects as $multimediaObject) {
            if (!$this->havePeopleMultimediaObject($multimediaObject)) {
                continue;
            }
            $peopleEmails = $this->getPeopleToNotifyOnMultimediaObject($multimediaObject);
            if ($peopleEmails) {
                $peopleToNotifyOnMultimediaObject[$multimediaObject->getId()] = $peopleEmails;
            }
        }

        $multimediaObjectsWithPeopleToNotify = $this->prepareEmailByPerson($peopleToNotifyOnMultimediaObject);

        $this->generateAndSendNotification($multimediaObjectsWithPeopleToNotify);

        return $peopleToNotifyOnMultimediaObject;
    }

    public function generateAndSendNotification(iterable $peopleEmailsAndMultimediaObjects)
    {
        if (!$this->senderService->isEnabled()) {
            return false;
        }

        $output = null;
        $parameters = [
            'subject' => $this->expiredVideoConfigurationService->getNotificationEmailConfiguration()['subject'],
            'sender_name' => $this->senderService->getSenderName(),
        ];

        foreach ($peopleEmailsAndMultimediaObjects as $userEmail => $multimediaObjects) {
            $aUserKeys = $this->addRenewUniqueKeys((string) $userEmail, $multimediaObjects);
            if (null === $aUserKeys) {
                continue;
            }
            $parameters['data'] = $aUserKeys;

            $output = $this->senderService->sendNotification(
                $userEmail,
                $this->translator->trans($this->expiredVideoConfigurationService->getNotificationEmailConfiguration()['subject']),
                $this->expiredVideoConfigurationService->getNotificationEmailConfiguration()['template'],
                $parameters,
                false
            );
        }

        return $output;
    }

    private function havePeopleMultimediaObject(MultimediaObject $multimediaObject): bool
    {
        $people = $multimediaObject->getPeopleByRoleCod($this->personService->getPersonalScopeRoleCode(), true);

        return count($people) > 0;
    }

    private function getPeopleToNotifyOnMultimediaObject(MultimediaObject $multimediaObject): array
    {
        $peopleEmails = [];
        $people = $multimediaObject->getPeopleByRoleCod($this->personService->getPersonalScopeRoleCode(), true);
        foreach ($people as $person) {
            $email = $this->getPersonEmail($person);
            if (null !== $email) {
                $peopleEmails[] = $email;
            }
        }

        return array_values(array_unique($peopleEmails));
    }

    private function getPersonEmail(PersonInterface $person): ?string
    {
        $email = trim((string) $person->getEmail());

        return '' === $email ? null : $email;
    }

    private function prepareEmailByPerson(array $elements): array
    {
        $peopleAndMultimediaObjects = [];
        foreach ($elements as $multimediaObjectID => $peopleEmails) {
            foreach ($peopleEmails as $personEmail) {
                $peopleAndMultimediaObjects[$personEmail][] = $multimediaObjectID;
            }
        }

        return $peopleAndMultimediaObjects;
    }

    private function addRenewUniqueKeys(string $userEmail, array $multimediaObjects): ?array
    {
        $person = $this->getPersonByEmail($userEmail);
        if (!$person instanceof Person) {
            return null;
        }

        $data = ['all' => $this->generateDataForPerson($person), 'videos' => []];
        foreach ($multimediaObjects as $multimediaObjectId) {
            $multimediaObjectData = $this->generateDataForMultimediaObject($multimediaObjectId);
            if (null !== $multimediaObjectData) {
                $data['videos'][$multimediaObjectId] = $multimediaObjectData;
            }
        }

        $this->documentManager->flush();

        return $data;
    }

    private function generateDataForPerson(Person $person): ObjectId
    {
        $renewUserToken = TokenUtils::generateExpiredToken();
        $person->setProperty(
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewKey(),
            $renewUserToken
        );

        return $renewUserToken;
    }

    private function generateDataForMultimediaObject(string $multimediaObjectId): ?array
    {
        $data = [];
        $mmObj = $this->getMultimediaObjectById($multimediaObjectId);
        if (!$mmObj instanceof MultimediaObject) {
            return null;
        }

        $renewMultimediaObjectToken = $mmObj->getProperty($this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewKey());
        if (empty($renewMultimediaObjectToken)) {
            $renewMultimediaObjectToken = TokenUtils::generateExpiredToken();
        }

        $mmObj->setProperty(
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewKey(),
            $renewMultimediaObjectToken
        );

        $data['token'] = $renewMultimediaObjectToken;
        $data['title'] = $mmObj->getTitle();
        $data['obj'] = $mmObj;
        $data['expired'] = $mmObj->getPropertyAsDateTime(
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey()
        );

        return $data;
    }

    private function getPersonByEmail(string $email): ?Person
    {
        return $this->documentManager->getRepository(Person::class)->findOneBy(['email' => $email]);
    }

    private function getMultimediaObjectById(string $multimediaObjectID): ?MultimediaObject
    {
        return $this->documentManager->getRepository(MultimediaObject::class)->findOneBy([
            '_id' => new ObjectId($multimediaObjectID),
        ]);
    }
}
