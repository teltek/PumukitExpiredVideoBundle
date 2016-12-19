<?php

namespace Pumukit\ExpiredVideoBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Pumukit\SchemaBundle\Document\MultimediaObject;

/**
 * @Route("/expired/video")
 */
class ExpiredVideoController extends Controller
{
    /**
     * @Route("/renew/{key}", name="pumukit_expired_video_renew")
     * @Template()
     */
    public function renewExpiredVideoAction(Request $request, $key)
    {
        $dm = $this->get('doctrine_mongodb')->getManager();

        // Comprobar que existe el Key suministrado
        // Realizar la renovación
        // Devolver un mensaje de fecha de actualización y confirmación


    }

}