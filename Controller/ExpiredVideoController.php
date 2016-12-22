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
    private $days = 365;
    private $roleCod;
    /**
     * @Route("/renew/{key}/", name="pumukit_expired_video_renew",  defaults={"key": null})
     * @Template()
     */
    public function renewExpiredVideoAction(Request $request, $key)
    {
        $regex = '/^[0-9a-z]{24}$/';
        if(!$key || !preg_match($regex, $key)) {
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
                $aRenew[] = $this->days;
                $mmObj->setProperty('renew_expiration_date', $aRenew);

                $mmObj->removeProperty('expiration_key');

                $date = new \DateTime();
                $date->add(new \DateInterval('P'.$this->days.'D'));
                $mmObj->setProperty('expiration_date', $date->format('c'));

                $dm->flush();

                $error = 0;
            } else {
                $error = 1;
            }
        } else {
            $error = 2;
        }

        return array('message' => $error);
    }
}