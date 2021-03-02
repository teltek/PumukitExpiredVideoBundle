<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\ExpiredVideoBundle\Exception\ExpiredVideoException;
use Pumukit\NotificationBundle\Services\SenderService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\SchemaBundle\Services\PersonService;
use Symfony\Component\Translation\TranslatorInterface;

class ExpiredVideoUpdateService
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
        $this->expiredVideoConfigurationService = $expiredVideoConfigurationService;
        $this->personService = $personService;
        $this->senderService = $senderService;
        $this->translator = $translator;
    }

    public function removeOwners(MultimediaObject $multimediaObject): array
    {
        if (0 === count($multimediaObject->getRoles())) {
            return [];
        }

        $removeOwner = false;
        $removedOwnerFromMultimediaObject = [];
        foreach ($multimediaObject->getRoles() as $role) {
            if ($role->getCod() !== $this->personService->getPersonalScopeRoleCode()) {
                continue;
            }

            foreach ($multimediaObject->getPeopleByRoleCod($this->personService->getPersonalScopeRoleCode(), true) as $person) {
                $multimediaObject->addPersonWithRole($person, $this->getRoleWithCode());
                $multimediaObject->removePersonWithRole($person, $role);
                $removedOwnerFromMultimediaObject[$multimediaObject->getId()][] = $person->getEmail();
            }
            $removeOwner = true;
        }

        if ($removeOwner) {
            $this->documentManager->flush();

            return $removedOwnerFromMultimediaObject;
        }

        return $removedOwnerFromMultimediaObject;
    }

    public function removeTag(MultimediaObject $multimediaObject): void
    {
        $multimediaObject->removeTag($this->getWebTVTag());
        $this->documentManager->flush();
    }

    public function sendAdministratorEmail(array $multimediaObjects)
    {
        if (!$this->senderService->isEnabled()) {
            return false;
        }

        $parameters = [
            'subject' => $this->expiredVideoConfigurationService->getUpdateEmailConfiguration()['subject'],
            'sender_name' => $this->senderService->getSenderName(),
            'multimedia_objects' => $multimediaObjects,
        ];

        return $this->senderService->sendNotification(
            $this->expiredVideoConfigurationService->getAdministratorEmails(),
            $this->translator->trans($parameters['subject']),
            $this->expiredVideoConfigurationService->getUpdateEmailConfiguration()['template'],
            $parameters,
            false
        );
    }

    private function getRoleWithCode(): Role
    {
        $role = $this->documentManager->getRepository(Role::class)->findOneBy([
            'cod' => $this->expiredVideoConfigurationService->getRoleCodeExpiredOwner(),
        ]);

        if (!$role instanceof Role) {
            throw new ExpiredVideoException("Role with code '".$this->expiredVideoConfigurationService->getRoleCodeExpiredOwner()."' not found. Please, init pumukit roles.");
        }

        return $role;
    }

    private function getWebTVTag(): Tag
    {
        $webTVTag = $this->documentManager->getRepository(Tag::class)->findOneBy(['cod' => 'PUCHWEBTV']);
        if (!$webTVTag instanceof Tag) {
            throw new \Exception('PUCHWEBTV tag not found');
        }

        return $webTVTag;
    }
}
