<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Services;

use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\PermissionProfile;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Services\MultimediaObjectService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class MultimediaObjectVoter extends Voter
{
    public const RENEW = 'renew';

    private $multimediaObjectService;

    public function __construct(MultimediaObjectService $multimediaObjectService)
    {
        $this->multimediaObjectService = $multimediaObjectService;
    }

    protected function supports($attribute, $subject): bool
    {
        if (self::RENEW !== $attribute) {
            return false;
        }

        // NOTE: Only vote on Post objects inside this voter
        if (!$subject instanceof MultimediaObject) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $multimediaObject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (self::RENEW === $attribute) {
            return $this->canRenew($this->multimediaObjectService, $multimediaObject, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    protected function canRenew(MultimediaObjectService $multimediaObjectService, MultimediaObject $multimediaObject, $user = null): bool
    {
        if ($user instanceof User && ($user->hasRole(PermissionProfile::SCOPE_GLOBAL) || $user->hasRole('ROLE_SUPER_ADMIN'))) {
            return true;
        }

        if ($user instanceof User && $user->hasRole(PermissionProfile::SCOPE_PERSONAL) && $multimediaObjectService->isUserOwner($user, $multimediaObject)) {
            return true;
        }

        return false;
    }
}
