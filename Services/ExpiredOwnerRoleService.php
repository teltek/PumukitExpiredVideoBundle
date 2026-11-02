<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Document\Role;

class ExpiredOwnerRoleService
{
    public function __construct(private readonly DocumentManager $documentManager) {}

    public function getRole(): Role
    {
        $role = $this->documentManager->getRepository(Role::class)->findOneBy(['cod' => ExpiredVideoConfigurationService::EXPIRED_OWNER_CODE]);
        if ($role instanceof Role) {
            return $role;
        }

        $role = new Role();
        $role->setCod(ExpiredVideoConfigurationService::EXPIRED_OWNER_CODE);
        $role->setXml(ExpiredVideoConfigurationService::EXPIRED_OWNER_CODE);
        $role->setDisplay(false);
        $role->setI18nName(['en' => 'Old owner', 'es' => 'Antiguo propietario', 'gl' => 'Antigo propietario', 'eu' => 'Jabe ohia']);
        $this->documentManager->persist($role);
        $this->documentManager->flush();

        return $role;
    }
}
