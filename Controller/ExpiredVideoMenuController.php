<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoConfigurationService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoService;
use Pumukit\NewAdminBundle\Controller\NewAdminControllerInterface;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Series;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/expired/video')]
class ExpiredVideoMenuController extends AbstractController implements NewAdminControllerInterface
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly ExpiredVideoConfigurationService $expiredVideoConfigurationService,
        private readonly ExpiredVideoService $expiredVideoService,
    ) {}

    #[Route('/info/{id}', name: 'pumukit_expired_video_info', methods: ['GET'])]
    #[IsGranted('ROLE_ACCESS_MULTIMEDIA_SERIES')]
    public function infoAction(MultimediaObject $multimediaObject): Response
    {
        $canEdit = $this->isGranted($this->expiredVideoConfigurationService->getAccessExpiredVideoCodePermission());

        return $this->render('@PumukitExpiredVideo/ExpiredVideo/info.html.twig', [
            'can_edit' => $canEdit,
            'multimediaObject' => $multimediaObject,
        ]);
    }

    #[Route('/update/date/{id}', name: 'pumukit_expired_video_update_date', methods: ['POST'])]
    #[IsGranted('ROLE_ACCESS_EXPIRED_VIDEO')]
    public function updateDateFromModalAction(Request $request, MultimediaObject $multimediaObject): RedirectResponse
    {
        $newDate = \DateTime::createFromFormat('!Y-m-d', (string) $request->request->get('date'));
        if (!$newDate) {
            throw new BadRequestHttpException('The date must have the format YYYY-MM-DD');
        }
        $multimediaObject->setPropertyAsDateTime(
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(),
            $newDate
        );

        $this->documentManager->persist($multimediaObject);
        $this->documentManager->flush();

        return $this->redirectToRoute('pumukit_expired_video_info', ['id' => $multimediaObject->getId()]);
    }

    #[Route('/renew/series/info/{id}', name: 'pumukit_expired_video_renew_list', methods: ['GET'])]
    #[IsGranted('ROLE_ACCESS_EXPIRED_VIDEO')]
    public function renewAllVideoOfSeriesFromMenuAction(Series $series): Response
    {
        $multimediaObjects = $this->documentManager->getRepository(MultimediaObject::class)->findBy(
            [
                'series' => $series->getId(),
                'status' => ['$ne' => MultimediaObject::STATUS_PROTOTYPE],
                'type' => ['$ne' => MultimediaObject::TYPE_LIVE],
            ]
        );

        return $this->render('@PumukitExpiredVideo/Modal/index.html.twig', [
            'multimediaObjects' => $multimediaObjects,
            'series' => $series,
        ]);
    }

    #[Route('/renew/all/series/{id}', name: 'pumukit_expired_series_renew_all', methods: ['POST'])]
    #[IsGranted('ROLE_ACCESS_EXPIRED_VIDEO')]
    public function renewAllSeriesAction(Request $request, Series $series): JsonResponse
    {
        $date = (string) $request->request->get('date');
        if ('' === $date) {
            $date = $this->expiredVideoService->getExpirationDateByPermission();
        } else {
            $date = \DateTime::createFromFormat('!Y-m-d', $date);
            if (!$date) {
                throw new BadRequestHttpException('The date must have the format YYYY-MM-DD');
            }
        }

        $multimediaObjects = $this->documentManager->getRepository(MultimediaObject::class)->findBy(
            [
                'series' => $series->getId(),
                'status' => ['$ne' => MultimediaObject::STATUS_PROTOTYPE],
                'type' => ['$ne' => MultimediaObject::TYPE_LIVE],
            ]
        );

        if ($multimediaObjects) {
            foreach ($multimediaObjects as $multimediaObject) {
                $this->expiredVideoService->renewMultimediaObject($multimediaObject, $date);
            }
        }

        return new JsonResponse(['success']);
    }
}
