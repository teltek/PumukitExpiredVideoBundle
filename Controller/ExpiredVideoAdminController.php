<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoConfigurationService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoDeleteService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoUpdateService;
use Pumukit\ExpiredVideoBundle\Utils\TokenUtils;
use Pumukit\NewAdminBundle\Controller\NewAdminControllerInterface;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Role;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/admin/expired/video/system")
 * @Security("is_granted('ROLE_ACCESS_EXPIRED_VIDEO')")
 */
class ExpiredVideoAdminController extends Controller implements NewAdminControllerInterface
{
    private $documentManager;
    private $expiredVideoConfigurationService;
    private $expiredVideoDeleteService;
    private $expiredVideoUpdateService;
    private $personalScopeRoleCode;

    public function __construct(
        DocumentManager $documentManager,
        ExpiredVideoConfigurationService $expiredVideoConfigurationService,
        ExpiredVideoDeleteService $expiredVideoDeleteService,
        ExpiredVideoUpdateService $expiredVideoUpdateService,
        string $personalScopeRoleCode
    ) {
        $this->documentManager = $documentManager;
        $this->expiredVideoConfigurationService = $expiredVideoConfigurationService;
        $this->expiredVideoDeleteService = $expiredVideoDeleteService;
        $this->expiredVideoUpdateService = $expiredVideoUpdateService;
        $this->personalScopeRoleCode = $personalScopeRoleCode;
    }

    /**
     * @Route("/list/", name="pumukit_expired_video_list")
     * @Template("@PumukitExpiredVideo/ExpiredVideo/list.html.twig")
     */
    public function listAction(): array
    {
        $ownerRol = $this->documentManager->getRepository(Role::class)->findOneBy([
            'cod' => $this->personalScopeRoleCode,
        ]);

        $now = new \DateTime();
        $date = $now->add(new \DateInterval('P'.$this->expiredVideoConfigurationService->getRangeWarningDays().'D'));
        $multimediaObjects = $this->documentManager->getRepository(MultimediaObject::class)->findBy(
            [
                $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(true) => [
                    '$lte' => $date->format('c'),
                ],
            ],
            [
                $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(true) => -1,
            ]
        );

        return [
            'days' => $this->expiredVideoConfigurationService->getExpirationDateDaysConf(),
            'ownerRol' => $ownerRol,
            'multimediaObjects' => $multimediaObjects,
        ];
    }

    /**
     * @Route("/delete/{key}/", name="pumukit_expired_video_delete", defaults={"key": null})
     * @Template("@PumukitExpiredVideo/ExpiredVideo/list.html.twig")
     */
    public function deleteVideoAction(string $key): RedirectResponse
    {
        if (!TokenUtils::isValidToken($key)) {
            return $this->redirectToRoute('homepage', [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $multimediaObject = $this->documentManager->getRepository(MultimediaObject::class)->find(new \MongoId($key));
        if ($multimediaObject) {
            $this->expiredVideoDeleteService->removeMultimediaObject($multimediaObject);
        }

        return $this->redirectToRoute('pumukit_expired_video_list', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @Route("/renew/{key}/", name="pumukit_expired_video_update", defaults={"key": null})
     * @Template("@PumukitExpiredVideo/ExpiredVideo/list.html.twig")
     */
    public function renewExpiredVideoAdminAction(string $key): RedirectResponse
    {
        if (!TokenUtils::isValidToken($key)) {
            return $this->redirectToRoute('homepage', [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $days = $this->expiredVideoConfigurationService->getExpirationDateDaysConf();
        $mmObj = $this->documentManager->getRepository(MultimediaObject::class)->find(new \MongoId($key));

        if ($mmObj) {
            $roleOwner = $this->documentManager->getRepository(Role::class)->findOneBy(['cod' => $this->personalScopeRoleCode]);
            foreach ($mmObj->getRoles() as $role) {
                if ($this->expiredVideoConfigurationService->getRoleCodeExpiredOwner() === $role->getCod()) {
                    foreach ($mmObj->getPeopleByRoleCod($this->expiredVideoConfigurationService->getRoleCodeExpiredOwner(), true) as $person) {
                        $mmObj->addPersonWithRole($person, $roleOwner);
                        $mmObj->removePersonWithRole($person, $role);
                    }
                }
            }

            $aRenew = $mmObj->getProperty(
                $this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewExpirationDateKey()
            );
            $aRenew[] = $days;
            $mmObj->setProperty(
                $this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewExpirationDateKey(),
                $aRenew
            );

            $mmObj->removeProperty(
                $this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewKey()
            );

            $date = new \DateTime();
            $date->add(new \DateInterval('P'.$days.'D'));
            $mmObj->setPropertyAsDateTime(
                $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(),
                $date
            );

            $this->expiredVideoUpdateService->postVideo($mmObj);

            $this->documentManager->flush();
        }

        return $this->redirectToRoute('pumukit_expired_video_list', [], Response::HTTP_MOVED_PERMANENTLY);
    }
}
