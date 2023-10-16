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
    private $documentManager;
    private $expiredVideoConfigurationService;
    private $personService;
    private $senderService;
    private $translator;

    public function __construct(
        DocumentManager $documentManager,
        ExpiredVideoConfigurationService $expiredVideoConfigurationService,
        PersonService $personService,
        SenderService $senderService,
        TranslatorInterface $translator
    ) {
        $this->documentManager = $documentManager;
        $this->personService = $personService;
        $this->senderService = $senderService;
        $this->translator = $translator;
        $this->expiredVideoConfigurationService = $expiredVideoConfigurationService;
    }

    public function checkAndSendNotifications(iterable $multimediaObjects): array
    {
        $peopleToNotifyOnMultimediaObject = [];
        foreach ($multimediaObjects as $multimediaObject) {
            if ($this->havePeopleMultimediaObject($multimediaObject)) {
                $peopleToNotifyOnMultimediaObject[$multimediaObject->getId()] = $this->getPeopleToNotifyOnMultimediaObject($multimediaObject);
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
            $aUserKeys = $this->addRenewUniqueKeys($userEmail, $multimediaObjects);
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
            $peopleEmails[] = $this->getPersonEmail($person);
        }

        return $peopleEmails;
    }

    private function getPersonEmail(PersonInterface $person): ?string
    {
        if ($person->getEmail()) {
            return $person->getEmail();
        }

        return null;
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

    private function addRenewUniqueKeys(string $userEmail, array $multimediaObjects): array
    {
        $data['all'] = $this->generateDataForPerson($userEmail);
        foreach ($multimediaObjects as $multimediaObjectId) {
            $data['videos'][$multimediaObjectId] = $this->generateDataForMultimediaObject($multimediaObjectId);
        }

        $this->documentManager->flush();

        return $data;
    }

    private function generateDataForPerson(string $personEmail)
    {
        $renewUserToken = TokenUtils::generateExpiredToken();
        $person = $this->getPersonByEmail($personEmail);
        $person->setProperty(
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewKey(),
            $renewUserToken
        );

        return $renewUserToken;
    }

    private function generateDataForMultimediaObject(string $multimediaObjectId): array
    {
        $mmObj = $this->getMultimediaObjectById($multimediaObjectId);

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

    private function getPersonByEmail(string $email)
    {
        return $this->documentManager->getRepository(Person::class)->findOneBy(['email' => $email]);
    }

    private function getMultimediaObjectById($multimediaObjectID)
    {
        return $this->documentManager->getRepository(MultimediaObject::class)->findOneBy([
            '_id' => new ObjectId($multimediaObjectID),
        ]);
    }
}
