<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Controller;

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
    /**
     * @Route("/renew/{key}/", name="pumukit_expired_video_owner_renew", defaults={"key": null})
     * @Template("PumukitExpiredVideoBundle:ExpiredVideo:renewExpiredVideo.html.twig")
     */
    public function renewMultimediaObjectFromEmailAction(string $key)
    {
        if (!TokenUtils::isValidToken($key)) {
            return $this->redirectToRoute('homepage', [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $expiredVideoRenewService = $this->container->get('pumukit_expired_video.renew');

        $multimediaObject = $expiredVideoRenewService->findVideoByRenewKey($key);
        if (!$multimediaObject) {
            return ['message' => 2,
                    'multimediaObjects' => $multimediaObject, ];
        }

        $isOwner = $expiredVideoRenewService->isOwner($multimediaObject, $this->getUser());
        if (!$isOwner) {
            return ['message' => 1];
        }

        $expiredVideoRenewService->renew($multimediaObject);

        return ['message' => 0];
    }

    /**
     * @Route("/all/renew/{key}/", name="pumukit_expired_video_owner_renew_all", defaults={"key": null})
     * @Template("PumukitExpiredVideoBundle:ExpiredVideo:renewExpiredVideo.html.twig")
     */
    public function renewAllMultimediaObjectsFromEmailAction(string $key)
    {
        if (!TokenUtils::isValidToken($key)) {
            return $this->redirectToRoute('homepage', [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $expiredVideoRenewService = $this->container->get('pumukit_expired_video.renew');

        $person = $expiredVideoRenewService->getPersonWithRenewKey($key);
        if (!$person) {
            return ['message' => 4];
        }

        $multimediaObjects = $expiredVideoRenewService->findMultimediaObjectsByPerson($person);
        if (!$multimediaObjects) {
            return ['message' => 2,
                    'multimediaObjects' => $multimediaObjects, ];
        }

        $expiredVideoRenewService->renewAllMultimediaObjects($multimediaObjects, $this->getUser(), $person);

        return ['message' => 0];
    }
}
