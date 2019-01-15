<?php

namespace Pumukit\ExpiredVideoBundle\Services;

use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\PermissionProfile;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Services\MultimediaObjectService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class MultimediaObjectVoter.
 */
class MultimediaObjectVoter extends Voter
{
    const RENEW = 'renew';

    private $multimediaObjectService;
    private $requestStack;

    /**
     * MultimediaObjectVoter constructor.
     *
     * @param MultimediaObjectService $multimediaObjectService
     * @param RequestStack            $requestStack
     */
    public function __construct(MultimediaObjectService $multimediaObjectService, RequestStack $requestStack)
    {
        $this->multimediaObjectService = $multimediaObjectService;
        $this->requestStack = $requestStack;
    }

    /**
     * @param string $attribute
     * @param mixed  $subject
     *
     * @return bool
     */
    protected function supports($attribute, $subject)
    {
        // NOTE: If the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::RENEW))) {
            return false;
        }

        // NOTE: Only vote on Post objects inside this voter
        if (!$subject instanceof MultimediaObject) {
            return false;
        }

        return true;
    }

    /**
     * @param string         $attribute
     * @param mixed          $multimediaObject
     * @param TokenInterface $token
     *
     * @return bool
     */
    protected function voteOnAttribute($attribute, $multimediaObject, TokenInterface $token)
    {
        $user = $token->getUser();

        switch ($attribute) {
            case self::RENEW:
                return $this->canRenew($this->multimediaObjectService, $multimediaObject, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    /**
     * @param MultimediaObjectService $multimediaObjectService
     * @param MultimediaObject        $multimediaObject
     * @param null                    $user
     *
     * @return bool
     */
    protected function canRenew(MultimediaObjectService $multimediaObjectService, MultimediaObject $multimediaObject, $user = null)
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
