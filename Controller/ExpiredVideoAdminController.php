<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Controller;

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
    /**
     * @Route("/list/", name="pumukit_expired_video_list")
     * @Template("PumukitExpiredVideoBundle:ExpiredVideo:list.html.twig")
     */
    public function listAction(): array
    {
        $expiredVideoConfigurationService = $this->container->get('pumukit_expired_video.configuration');

        $documentManager = $this->get('doctrine_mongodb.odm.document_manager');
        $ownerRol = $documentManager->getRepository(Role::class)->findOneBy([
            'cod' => $this->container->getParameter('pumukitschema.personal_scope_role_code'),
        ]);

        $now = new \DateTime();
        $date = $now->add(new \DateInterval('P'.$expiredVideoConfigurationService->getRangeWarningDays().'D'));
        $multimediaObjects = $documentManager->getRepository(MultimediaObject::class)->findBy(
            [
                $expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(true) => [
                    '$lte' => $date->format('c'),
                ],
            ],
            [
                $expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(true) => -1,
            ]
        );

        return [
            'days' => $expiredVideoConfigurationService->getExpirationDateDaysConf(),
            'ownerRol' => $ownerRol,
            'multimediaObjects' => $multimediaObjects,
        ];
    }

    /**
     * @Route("/delete/{key}/", name="pumukit_expired_video_delete", defaults={"key": null})
     * @Template("PumukitExpiredVideoBundle:ExpiredVideo:list.html.twig")
     */
    public function deleteVideoAction(string $key): RedirectResponse
    {
        if (!TokenUtils::isValidToken($key)) {
            return $this->redirectToRoute('homepage', [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $documentManager = $this->get('doctrine_mongodb.odm.document_manager');
        $expiredVideoDeleteService = $this->get('pumukit_expired_video.delete');

        $multimediaObject = $documentManager->getRepository(MultimediaObject::class)->find(new \MongoId($key));
        if ($multimediaObject) {
            $expiredVideoDeleteService->removeMultimediaObject($multimediaObject);
        }

        return $this->redirectToRoute('pumukit_expired_video_list', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @Route("/renew/{key}/", name="pumukit_expired_video_update", defaults={"key": null})
     * @Template("PumukitExpiredVideoBundle:ExpiredVideo:list.html.twig")
     */
    public function renewExpiredVideoAdminAction(string $key): RedirectResponse
    {
        if (!TokenUtils::isValidToken($key)) {
            return $this->redirectToRoute('homepage', [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $ownerKey = $this->container->getParameter('pumukitschema.personal_scope_role_code');
        $expiredVideoConfigurationService = $this->container->get('pumukit_expired_video.configuration');
        $days = $expiredVideoConfigurationService->getExpirationDateDaysConf();

        $dm = $this->get('doctrine_mongodb.odm.document_manager');
        $mmObj = $dm->getRepository(MultimediaObject::class)->find(new \MongoId($key));

        if ($mmObj) {
            $roleOwner = $dm->getRepository(Role::class)->findOneBy(['cod' => $ownerKey]);
            foreach ($mmObj->getRoles() as $role) {
                if ($expiredVideoConfigurationService->getRoleCodeExpiredOwner() === $role->getCod()) {
                    foreach ($mmObj->getPeopleByRoleCod($expiredVideoConfigurationService->getRoleCodeExpiredOwner(), true) as $person) {
                        $mmObj->addPersonWithRole($person, $roleOwner);
                        $mmObj->removePersonWithRole($person, $role);
                    }
                }
            }

            $aRenew = $mmObj->getProperty(
                $expiredVideoConfigurationService->getMultimediaObjectPropertyRenewExpirationDateKey()
            );
            $aRenew[] = $days;
            $mmObj->setProperty(
                $expiredVideoConfigurationService->getMultimediaObjectPropertyRenewExpirationDateKey(),
                $aRenew
            );

            $mmObj->removeProperty(
                $expiredVideoConfigurationService->getMultimediaObjectPropertyRenewKey()
            );

            $date = new \DateTime();
            $date->add(new \DateInterval('P'.$days.'D'));
            $mmObj->setPropertyAsDateTime(
                $expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(),
                $date
            );

            $dm->flush();
        }

        return $this->redirectToRoute('pumukit_expired_video_list', [], Response::HTTP_MOVED_PERMANENTLY);
    }
}
