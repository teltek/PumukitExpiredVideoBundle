<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Controller;

use Pumukit\ExpiredVideoBundle\PumukitExpiredVideoBundle;
use Pumukit\NewAdminBundle\Controller\NewAdminControllerInterface;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Series;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/admin/expired/video")
 */
class ExpiredVideoMenuController extends Controller implements NewAdminControllerInterface
{
    /**
     * @Route("/info/{id}", name="pumukit_expired_video_info")
     * @Template("PumukitExpiredVideoBundle:ExpiredVideo:info.html.twig")
     * @Security("is_granted('ROLE_ACCESS_MULTIMEDIA_SERIES')")
     */
    public function infoAction(MultimediaObject $multimediaObject): array
    {
        $expiredVideoConfigurationService = $this->container->get('pumukit_expired_video.configuration');
        $canEdit = $this->isGranted($expiredVideoConfigurationService->getAccessExpiredVideoCodePermission());

        return ['can_edit' => $canEdit, 'multimediaObject' => $multimediaObject];
    }

    /**
     * @Route("/update/date/{id}", name="pumukit_expired_video_update_date")
     * @Security("is_granted('ROLE_ACCESS_EXPIRED_VIDEO')")
     */
    public function updateDateFromModalAction(Request $request, MultimediaObject $multimediaObject): RedirectResponse
    {
        $dm = $this->get('doctrine_mongodb.odm.document_manager');
        $expiredVideoConfigurationService = $this->container->get('pumukit_expired_video.configuration');

        $newDate = new \DateTime($request->get('date'));
        $multimediaObject->setPropertyAsDateTime(
            $expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(),
            $newDate
        );

        $dm->persist($multimediaObject);
        $dm->flush();

        return $this->redirectToRoute('pumukit_expired_video_info', ['id' => $multimediaObject->getId()]);
    }

    /**
     * @Route("/renew/series/info/{id}", name="pumukit_expired_video_renew_list")
     * @Security("is_granted('ROLE_ACCESS_EXPIRED_VIDEO')")
     * @Template("PumukitExpiredVideoBundle:Modal:index.html.twig")
     */
    public function renewAllVideoOfSeriesFromMenuAction(Series $series): array
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
