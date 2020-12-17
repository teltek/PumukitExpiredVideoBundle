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
 * @Route("/admin/expired/video")
 */
class ExpiredVideoAdminController extends Controller implements NewAdminControllerInterface
{
    /**
     * @Route("/list/", name="pumukit_expired_video_list")
     * @Template("PumukitExpiredVideoBundle:ExpiredVideo:listAll.html.twig")
     * @Security("is_granted('ROLE_ACCESS_EXPIRED_VIDEO')")
     */
    public function listAction(): array
    {
        $dm = $this->get('doctrine_mongodb.odm.document_manager');

        $expiredVideoConfigurationService = $this->container->get('pumukit_expired_video.configuration');

        $days = $expiredVideoConfigurationService->getExpirationDateDaysConf();
        $range_days = $expiredVideoConfigurationService->getRangeWarningDays();
        $ownerKey = $this->container->getParameter('pumukitschema.personal_scope_role_code');

        $ownerRol = $dm->getRepository(Role::class)->findOneBy([
            'cod' => $ownerKey,
        ]);

        $now = new \DateTime();
        $date = $now->add(new \DateInterval('P'.$range_days.'D'));
        $multimediaObjects = $dm->getRepository(MultimediaObject::class)->findBy(
            [
                'properties.expiration_date' => [
                    '$lte' => $date->format('c'),
                ],
            ],
            ['properties.expiration_date' => -1]
        );

        return [
            'days' => $days,
            'ownerRol' => $ownerRol,
            'multimediaObjects' => $multimediaObjects,
        ];
    }

    /**
     * @Route("/delete/{key}/", name="pumukit_expired_video_delete", defaults={"key": null})
     * @Template("PumukitExpiredVideoBundle:ExpiredVideo:listAll.html.twig")
     * @Security("is_granted('ROLE_ACCESS_EXPIRED_VIDEO')")
     */
    public function deleteVideoAction(string $key): RedirectResponse
    {
        if (!TokenUtils::isValidToken($key)) {
            return $this->redirectToRoute('homepage', [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $dm = $this->get('doctrine_mongodb.odm.document_manager');

        $multimediaObject = $dm->getRepository(MultimediaObject::class)->find(new \MongoId($key));
        if ($multimediaObject) {
            $dm->getRepository(MultimediaObject::class)->createQueryBuilder()
                ->remove()
                ->field('_id')->equals(new \MongoId($multimediaObject->getId()))
                ->getQuery()
                ->execute()
            ;
        }

        return $this->redirectToRoute('pumukit_expired_video_list', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @Route("/renew/{key}/", name="pumukit_expired_video_update", defaults={"key": null})
     * @Template("PumukitExpiredVideoBundle:ExpiredVideo:listAll.html.twig")
     * @Security("is_granted('ROLE_ACCESS_EXPIRED_VIDEO')")
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
