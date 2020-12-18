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
        return !(self::RENEW !== $attribute || !$subject instanceof MultimediaObject);
    }

    protected function voteOnAttribute($attribute, $multimediaObject, TokenInterface $token): bool
    {
        if (self::RENEW === $attribute) {
            return $this->canRenew($multimediaObject, $token->getUser());
        }

        throw new \LogicException('This code should not be reached!');
    }

    protected function canRenew(MultimediaObject $multimediaObject, $user = null): bool
    {
        if ($user instanceof User) {
            return $this->checkPermissionProfile($multimediaObject, $user);
        }

        return false;
    }

    private function checkPermissionProfile(MultimediaObject $multimediaObject, User $user): bool
    {
        $canRenew = false;
        if ($user->hasRole(PermissionProfile::SCOPE_GLOBAL) || $user->hasRole('ROLE_SUPER_ADMIN')) {
            $canRenew = true;
        }

        if ($user->hasRole(PermissionProfile::SCOPE_PERSONAL) && $this->multimediaObjectService->isUserOwner($user, $multimediaObject)) {
            $canRenew = true;
        }

        return $canRenew;
    }
}
