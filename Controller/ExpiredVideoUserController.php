<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Controller;

use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoRenewService;
use Pumukit\ExpiredVideoBundle\Utils\TokenUtils;
use Pumukit\NewAdminBundle\Controller\NewAdminControllerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/admin/expired/video")
 */
class ExpiredVideoUserController extends Controller implements NewAdminControllerInterface
{
    private $expiredVideoRenewService;

    public function __construct(ExpiredVideoRenewService $expiredVideoRenewService)
    {
        $this->expiredVideoRenewService = $expiredVideoRenewService;
    }

    /**
     * @Route("/renew/{key}/", name="pumukit_expired_video_owner_renew", defaults={"key": null})
     * @Template("@PumukitExpiredVideo/ExpiredVideo/renewExpiredVideo.html.twig")
     */
    public function renewMultimediaObjectFromEmailAction(string $key): array
    {
        if (!TokenUtils::isValidToken($key)) {
            return $this->redirectToRoute('homepage', [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $multimediaObject = $this->expiredVideoRenewService->findVideoByRenewKey($key);
        if (!$multimediaObject) {
            return ['message' => 2];
        }

        $isOwner = $this->expiredVideoRenewService->isOwner($multimediaObject, $this->getUser());
        if (!$isOwner) {
            return ['message' => 1];
        }

        $this->expiredVideoRenewService->renew($multimediaObject);

        return ['message' => 0];
    }

    /**
     * @Route("/all/renew/{key}/", name="pumukit_expired_video_owner_renew_all", defaults={"key": null})
     * @Template("@PumukitExpiredVideo/ExpiredVideo/renewExpiredVideo.html.twig")
     */
    public function renewAllMultimediaObjectsFromEmailAction(string $key): array
    {
        if (!TokenUtils::isValidToken($key)) {
            return $this->redirectToRoute('homepage', [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $person = $this->expiredVideoRenewService->getPersonWithRenewKey($key);
        if (!$person) {
            return ['message' => 4];
        }

        $multimediaObjects = $this->expiredVideoRenewService->findMultimediaObjectsByPerson($person);
        if (!$multimediaObjects) {
            return ['message' => 2];
        }

        $this->expiredVideoRenewService->renewAllMultimediaObjects($multimediaObjects, $this->getUser(), $person);

        return ['message' => 0];
    }
}
