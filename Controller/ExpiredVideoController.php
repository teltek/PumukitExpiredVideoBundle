<?php

namespace Pumukit\ExpiredVideoBundle\Controller;

use Pumukit\SchemaBundle\Document\Person;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/admin/expired/video")
 */
class ExpiredVideoController extends Controller
{
    private $renew_days;
    private $roleCod;
    private $regex = '/^[0-9a-z]{24}$/';

    /**
     * @Route("/renew/{key}/", name="pumukit_expired_video_renew",  defaults={"key": null})
     * @Template()
     */
    public function renewExpiredVideoAction(Request $request, $key)
    {
        $days = $this->container->getParameter('pumukit_expired_video.expiration_date_days');
        if(!$key || !preg_match($this->regex, $key)) {
            return $this->redirectToRoute('homepage', array(), 301);
        }

        $dm = $this->get('doctrine_mongodb')->getManager();
        $mmObj = $dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findOneBy(
            array('properties.expiration_key' => new \MongoId($key))
        );

        $user = $this->get('security.context')->getToken()->getUser();
        $this->roleCod = $this->container->getParameter('pumukitschema.personal_scope_role_code');

        if($mmObj) {
            $people = $mmObj->getPeopleByRoleCod($this->roleCod, true);
            $isOwner = false;
            if (isset($people) and !empty($people) and is_array($people)) {
                foreach ($people as $person) {
                    if ($person->getEmail() == $user->getEmail()) {
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
                $mmObj->setProperty('expiration_date', $date->format('c'));

                $dm->flush();

                $error = 0;
            } else {
                $error = 1;
            }
        } else {
            $error = 0;
        }

        return array('message' => $error);
    }

    /**
     * @Route("/all/renew/{key}/", name="pumukit_expired_video_renew_all",  defaults={"key": null})
     * @Template("PumukitExpiredVideoBundle:ExpiredVideo:renewExpiredVideo.html.twig")
     */
    public function renewAllExpiredVideoAction(Request $request, $key)
    {
        $days = $this->container->getParameter('pumukit_expired_video.expiration_date_days');

        if(!$key || !preg_match($this->regex, $key)) {
            return $this->redirectToRoute('homepage', array(), 301);
        }

        $dm = $this->get('doctrine_mongodb')->getManager();
        $person = $dm->getRepository('PumukitSchemaBundle:Person')->findOneBy(array('properties.expiration_key' => new \MongoId($key)));

        $user = $this->get('security.context')->getToken()->getUser();
        if($user->getEmail() == $person->getEmail()) {

            $aObject = $dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findBy(
                array('people.people._id' => $person->getId(), 'properties.expiration_key' => array('$exists' => true))
            );

            foreach ($aObject as $mmObj) {
                $aRenew = $mmObj->getProperty('renew_expiration_date');
                $aRenew[] = $days;
                $mmObj->setProperty('renew_expiration_date', $aRenew);

                $mmObj->removeProperty('expiration_key');

                $date = new \DateTime();
                $date->add(new \DateInterval('P'.$days.'D'));
                $mmObj->setProperty('expiration_date', $date->format('c'));

                $person->removeProperty('expiration_date');

            }
            $dm->flush();
            $error = 0;
        } else {
            $error = 1;
        }

        return array('message' => $error);
    }
}