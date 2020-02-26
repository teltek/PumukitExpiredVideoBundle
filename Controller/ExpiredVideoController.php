<?php

namespace Pumukit\ExpiredVideoBundle\Controller;

use Pumukit\NewAdminBundle\Controller\NewAdminControllerInterface;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Person;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\Series;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/admin/expired/video")
 */
class ExpiredVideoController extends Controller implements NewAdminControllerInterface
{
    private $roleCod;
    private $regex = '/^[0-9a-z]{24}$/';

    /**
     * List all expired multimedia object and the mmo that will be expired on range warning days.
     *
     * @Route("/list/", name="pumukit_expired_video_list_all")
     * @Template("PumukitExpiredVideoBundle:ExpiredVideo:listAll.html.twig")
     * @Security("is_granted('ROLE_ACCESS_EXPIRED_VIDEO')")
     */
    public function listAllAction(): array
    {
        $dm = $this->get('doctrine_mongodb.odm.document_manager');

        $days = $this->container->getParameter('pumukit_expired_video.expiration_date_days');
        $range_days = $this->container->getParameter('pumukit_expired_video.range_warning_days');
        $ownerKey = $this->container->getParameter('pumukitschema.personal_scope_role_code');

        $ownerRol = $dm->getRepository(Role::class)->findOneBy(['cod' => $ownerKey]);

        $now = new \DateTime();
        $date = $now->add(new \DateInterval('P'.$range_days.'D'));
        $aMultimediaObject = $dm->getRepository(MultimediaObject::class)->findBy(['properties.expiration_date' => ['$lte' => $date->format('c')]], ['properties.expiration_date' => -1]);

        return ['days' => $days, 'ownerRol' => $ownerRol, 'multimediaObjects' => $aMultimediaObject];
    }

    /**
     * @Route("/delete/{key}/", name="pumukit_expired_video_delete", defaults={"key": null})
     * @Template("PumukitExpiredVideoBundle:ExpiredVideo:listAll.html.twig")
     * @Security("is_granted('ROLE_ACCESS_EXPIRED_VIDEO')")
     */
    public function deleteVideoAction(string $key): RedirectResponse
    {
        if (!$key || !preg_match($this->regex, $key)) {
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

        return $this->redirectToRoute('pumukit_expired_video_list_all', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * Renew expired multimedia object and change rol of expired owner to owner.
     *
     * @Route("/renew/admin/{key}/", name="pumukit_expired_video_update", defaults={"key": null})
     * @Template("PumukitExpiredVideoBundle:ExpiredVideo:listAll.html.twig")
     * @Security("is_granted('ROLE_ACCESS_EXPIRED_VIDEO')")
     */
    public function renewExpiredVideoAdminAction(string $key): RedirectResponse
    {
        if (!$key || !preg_match($this->regex, $key)) {
            return $this->redirectToRoute('homepage', [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $days = $this->container->getParameter('pumukit_expired_video.expiration_date_days');
        $ownerKey = $this->container->getParameter('pumukitschema.personal_scope_role_code');

        $dm = $this->get('doctrine_mongodb.odm.document_manager');
        $mmObj = $dm->getRepository(MultimediaObject::class)->find(new \MongoId($key));

        if ($mmObj) {
            $roleOwner = $dm->getRepository(Role::class)->findOneBy(['cod' => $ownerKey]);
            foreach ($mmObj->getRoles() as $role) {
                if ('expired_owner' === $role->getCod()) {
                    foreach ($mmObj->getPeopleByRoleCod('expired_owner', true) as $person) {
                        $mmObj->addPersonWithRole($person, $roleOwner);
                        $mmObj->removePersonWithRole($person, $role);
                    }
                }
            }

            $aRenew = $mmObj->getProperty('renew_expiration_date');
            $aRenew[] = $days;
            $mmObj->setProperty('renew_expiration_date', $aRenew);

            $mmObj->removeProperty('expiration_key');

            $date = new \DateTime();
            $date->add(new \DateInterval('P'.$days.'D'));
            $mmObj->setPropertyAsDateTime('expiration_date', $date);

            $dm->flush();
        }

        return $this->redirectToRoute('pumukit_expired_video_list_all', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @Route("/renew/{key}/", name="pumukit_expired_video_renew",  defaults={"key": null})
     * @Template("PumukitExpiredVideoBundle:ExpiredVideo:renewExpiredVideo.html.twig")
     */
    public function renewExpiredVideoAction(string $key)
    {
        $days = $this->container->getParameter('pumukit_expired_video.expiration_date_days');
        if (!$key || !preg_match($this->regex, $key)) {
            return $this->redirectToRoute('homepage', [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $dm = $this->get('doctrine_mongodb.odm.document_manager');
        $mmObj = $dm->getRepository(MultimediaObject::class)->findOneBy(
            ['properties.expiration_key' => new \MongoId($key)]
        );

        $user = $this->getUser();
        $this->roleCod = $this->container->getParameter('pumukitschema.personal_scope_role_code');

        if ($mmObj) {
            $people = $mmObj->getPeopleByRoleCod($this->roleCod, true);
            $isOwner = false;
            if (isset($people) && !empty($people) && is_array($people)) {
                foreach ($people as $person) {
                    if ($person->getEmail() === $user->getEmail()) {
                        $isOwner = true;
                    }
                }
            }

            if ($isOwner) {
                $aRenew = $mmObj->getProperty('renew_expiration_date');
                $aRenew[] = $days;
                $mmObj->setProperty('renew_expiration_date', $aRenew);

                $mmObj->removeProperty('expiration_key');

                $date = new \DateTime();
                $date->add(new \DateInterval('P'.$days.'D'));
                $mmObj->setPropertyAsDateTime('expiration_date', $date);

                $dm->flush();

                $error = 0;
            } else {
                $error = 1;
            }
        } else {
            $error = 2;
        }

        return ['message' => $error];
    }

    /**
     * @Route("/all/renew/{key}/", name="pumukit_expired_video_renew_all",  defaults={"key": null})
     * @Template("PumukitExpiredVideoBundle:ExpiredVideo:renewExpiredVideo.html.twig")
     */
    public function renewAllExpiredVideoAction(string $key)
    {
        $days = $this->container->getParameter('pumukit_expired_video.expiration_date_days');

        if (!$key || !preg_match($this->regex, $key)) {
            return $this->redirectToRoute('homepage', [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $dm = $this->get('doctrine_mongodb.odm.document_manager');
        $person = $dm->getRepository(Person::class)->findOneBy(['properties.expiration_key' => new \MongoId($key)]);

        if (!$person) {
            $error = 4;

            return ['message' => $error];
        }

        $user = $this->getUser();
        if ($user->getEmail() === $person->getEmail()) {
            $aObject = $dm->getRepository(MultimediaObject::class)->findBy(
                ['people.people._id' => $person->getId(), 'properties.expiration_key' => ['$exists' => true]]
            );

            if (count($aObject) >= 1) {
                foreach ($aObject as $mmObj) {
                    $aRenew = $mmObj->getProperty('renew_expiration_date');
                    $aRenew[] = $days;
                    $mmObj->setProperty('renew_expiration_date', $aRenew);

                    $mmObj->removeProperty('expiration_key');

                    $date = new \DateTime();
                    $date->add(new \DateInterval('P'.$days.'D'));
                    $mmObj->setPropertyAsDateTime('expiration_date', $date);
                }

                $person->removeProperty('expiration_date');
                $dm->flush();
                $error = 0;
            } else {
                $error = 2;
            }
        } else {
            $error = 1;
        }

        return ['message' => $error];
    }

    /**
     * Used for modal window in MultimediaObjectMenuService.
     *
     * @Route("/info/{id}", name="pumukit_expired_video_info")
     * @Template("PumukitExpiredVideoBundle:ExpiredVideo:info.html.twig")
     * @Security("is_granted('ROLE_ACCESS_MULTIMEDIA_SERIES')")
     */
    public function infoAction(MultimediaObject $multimediaObject): array
    {
        $canEdit = $this->isGranted('ROLE_ACCESS_EXPIRED_VIDEO');

        return ['can_edit' => $canEdit, 'multimediaObject' => $multimediaObject];
    }

    /**
     * Update expiration date of a multimedia object (used in info modal).
     *
     * @Route("/update/date/{id}", name="pumukit_expired_video_update_date")
     * @Security("is_granted('ROLE_ACCESS_EXPIRED_VIDEO')")
     */
    public function updateDateAction(Request $request, MultimediaObject $multimediaObject): RedirectResponse
    {
        $dm = $this->get('doctrine_mongodb.odm.document_manager');

        $newDate = new \DateTime($request->get('date'));
        $multimediaObject->setPropertyAsDateTime('expiration_date', $newDate);

        $dm->persist($multimediaObject);
        $dm->flush();

        return $this->redirectToRoute('pumukit_expired_video_info', ['id' => $multimediaObject->getId()]);
    }

    /**
     * Modal to show all renewed videos.
     *
     * @Route("/renew/series/info/{id}", name="pumukit_expired_video_renew_list")
     * @Security("is_granted('ROLE_ACCESS_EXPIRED_VIDEO')")
     * @Template("PumukitExpiredVideoBundle:Modal:index.html.twig")
     */
    public function renewAllSeriesMenuAction(Series $series): array
    {
        $dm = $this->get('doctrine_mongodb.odm.document_manager');
        $multimediaObjects = $dm->getRepository(MultimediaObject::class)->findBy(
            [
                'series' => $series->getId(),
                'status' => ['$ne' => MultimediaObject::STATUS_PROTOTYPE],
                'type' => ['$ne' => MultimediaObject::TYPE_LIVE],
            ]
        );

        return [
            'multimediaObjects' => $multimediaObjects,
            'series' => $series,
        ];
    }

    /**
     * Renew all series.
     *
     * @Route("/renew/all/series/{id}", name="pumukit_expired_series_renew_all")
     * @Security("is_granted('ROLE_ACCESS_EXPIRED_VIDEO')")
     */
    public function renewAllSeriesAction(Request $request, Series $series): JsonResponse
    {
        $date = $request->get('date');
        $expiredVideoService = $this->get('pumukit_expired_video.expired_video');
        if (!$date) {
            $date = $expiredVideoService->getExpirationDateByPermission();
        } else {
            $date = \DateTime::createFromFormat('Y-m-d', $date);
        }

        $dm = $this->get('doctrine_mongodb.odm.document_manager');
        $multimediaObjects = $dm->getRepository(MultimediaObject::class)->findBy(
            [
                'series' => $series->getId(),
                'status' => ['$ne' => MultimediaObject::STATUS_PROTOTYPE],
                'type' => ['$ne' => MultimediaObject::TYPE_LIVE],
            ]
        );

        if ($multimediaObjects) {
            foreach ($multimediaObjects as $multimediaObject) {
                $expiredVideoService->renewMultimediaObject($multimediaObject, $date);
            }
        }

        return new JsonResponse(['success']);
    }
}
