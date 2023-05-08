<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Controller;

use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoRenewService;
use Pumukit\ExpiredVideoBundle\Utils\TokenUtils;
use Pumukit\NewAdminBundle\Controller\NewAdminControllerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/expired/video")
 */
class ExpiredVideoUserController extends AbstractController implements NewAdminControllerInterface
{
    private $expiredVideoRenewService;

    public function __construct(ExpiredVideoRenewService $expiredVideoRenewService)
    {
        $this->expiredVideoRenewService = $expiredVideoRenewService;
    }

    /**
     * @Route("/renew/{key}/", name="pumukit_expired_video_owner_renew", defaults={"key": null})
     */
    public function renewMultimediaObjectFromEmailAction(string $key)
    {
        if (!TokenUtils::isValidToken($key)) {
            return $this->redirectToRoute('homepage', [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $multimediaObject = $this->expiredVideoRenewService->findVideoByRenewKey($key);
        if (!$multimediaObject) {
            return $this->render('@PumukitExpiredVideo/ExpiredVideo/renewExpiredVideo.html.twig', ['message' => 2]);
        }

        $isOwner = $this->expiredVideoRenewService->isOwner($multimediaObject, $this->getUser());
        if (!$isOwner) {
            return $this->render('@PumukitExpiredVideo/ExpiredVideo/renewExpiredVideo.html.twig', ['message' => 1]);
        }

        $this->expiredVideoRenewService->renew($multimediaObject);

        return $this->render('@PumukitExpiredVideo/ExpiredVideo/renewExpiredVideo.html.twig', ['message' => 0]);
    }

    /**
     * @Route("/all/renew/{key}/", name="pumukit_expired_video_owner_renew_all", defaults={"key": null})
     */
    public function renewAllMultimediaObjectsFromEmailAction(string $key)
    {
        if (!TokenUtils::isValidToken($key)) {
            return $this->redirectToRoute('homepage', [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $person = $this->expiredVideoRenewService->getPersonWithRenewKey($key);
        if (!$person) {
            return $this->render('@PumukitExpiredVideo/ExpiredVideo/renewExpiredVideo.html.twig', ['message' => 4]);
        }

        $multimediaObjects = $this->expiredVideoRenewService->findMultimediaObjectsByPerson($person);
        if (!$multimediaObjects) {
            return $this->render('@PumukitExpiredVideo/ExpiredVideo/renewExpiredVideo.html.twig', ['message' => 2]);
        }

        $this->expiredVideoRenewService->renewAllMultimediaObjects($multimediaObjects, $this->getUser(), $person);

        return $this->render('@PumukitExpiredVideo/ExpiredVideo/renewExpiredVideo.html.twig', ['message' => 0]);
    }
}
