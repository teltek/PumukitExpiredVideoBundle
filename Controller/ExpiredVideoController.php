<?php

namespace Pumukit\ExpiredVideoBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * @Route("/admin/expired/video")
 */
class ExpiredVideoController extends Controller
{
    private $roleCod;
    private $regex = '/^[0-9a-z]{24}$/';

    /**
     * List all expired multimedia object and the mmo that will be expired on range warning days
     *
     * @Route("/list/", name="pumukit_expired_video_list_all")
     * @Template()
     * @Security("is_granted('ROLE_ACCESS_EXPIRED_VIDEO')")
     */
    public function listAll()
    {
        $dm = $this->get('doctrine_mongodb')->getManager();

        $days = $this->container->getParameter('pumukit_expired_video.expiration_date_days');
        $range_days = $this->container->getParameter('pumukit_expired_video.range_warning_days');
        $ownerKey = $this->container->getParameter('pumukitschema.personal_scope_role_code');

        $now = new \DateTime();
        $date = $now->add(new \DateInterval('P' . $range_days . 'D'));
        $aMultimediaObject = $dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findBy(array('properties.expiration_date' => array('$exists' => true), 'properties.expiration_date' => array('$lte' => $date->format('c'))),array('properties.expiration_date' => -1));

        return array('days' => $days, 'ownerKey' => $ownerKey, 'multimediaObjects' => $aMultimediaObject);
    }

    /**
     * Delete multimedia object
     *
     * @Route("/delete/{key}/", name="pumukit_expired_video_delete", defaults={"key": null})
     * @Template("PumukitExpiredVideoBundle:ExpiredVideo:listAll.html.twig")
     * @Security("is_granted('ROLE_ACCESS_EXPIRED_VIDEO')")
     */
    public function deleteVideoAction(Request $request, $key)
    {
        if (!$key || !preg_match($this->regex, $key)) {
            return $this->redirectToRoute('homepage', array(), 301);
        }

        $dm = $this->get('doctrine_mongodb')->getManager();

        $multimediaObject = $dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findOneById(new \MongoId($key));
        if(isset($multimediaObject)) {
            $sResult = $dm->getRepository('PumukitSchemaBundle:MultimediaObject')->createQueryBuilder()
                ->remove()
                ->field('_id')->equals(new \MongoId($multimediaObject->getId()))
                ->getQuery()
                ->execute();
        }

        return $this->redirectToRoute('pumukit_expired_video_list_all', array(), 301);
    }

    /**
     * Renew expired multimedia object and change rol of expired owner to owner.
     *
     * @Route("/renew/admin/{key}/", name="pumukit_expired_video_update", defaults={"key": null})
     * @Template("PumukitExpiredVideoBundle:ExpiredVideo:listAll.html.twig")
     * @Security("is_granted('ROLE_ACCESS_EXPIRED_VIDEO')")
     */
    public function renewExpiredVideoAdminAction(Request $request, $key)
    {
        if (!$key || !preg_match($this->regex, $key)) {
            return $this->redirectToRoute('homepage', array(), 301);
        }

        $days = $this->container->getParameter('pumukit_expired_video.expiration_date_days');
        $ownerKey = $this->container->getParameter('pumukitschema.personal_scope_role_code');

        $dm = $this->get('doctrine_mongodb')->getManager();
        $mmObj = $dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findOneById(new \MongoId($key));

        if ($mmObj) {

            $roleOwner = $dm->getRepository('PumukitSchemaBundle:Role')->findOneByCod($ownerKey);
            foreach ($mmObj->getRoles() as $role) {
                if ($role->getCod() == 'expired_owner') {
                    foreach ($mmObj->getPeopleByRoleCod($ownerKey, true) as $person) {
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

        return $this->redirectToRoute('pumukit_expired_video_list_all', array(), 301);
    }

    /**
     * @Route("/renew/{key}/", name="pumukit_expired_video_renew",  defaults={"key": null})
     * @Template()
     */
    public function renewExpiredVideoAction(Request $request, $key)
    {
        $days = $this->container->getParameter('pumukit_expired_video.expiration_date_days');
        if (!$key || !preg_match($this->regex, $key)) {
            return $this->redirectToRoute('homepage', array(), 301);
        }

        $dm = $this->get('doctrine_mongodb')->getManager();
        $mmObj = $dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findOneBy(
            array('properties.expiration_key' => new \MongoId($key))
        );

        $user = $this->get('security.context')->getToken()->getUser();
        $this->roleCod = $this->container->getParameter('pumukitschema.personal_scope_role_code');

        if ($mmObj) {
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
                $mmObj->setPropertyAsDateTime('expiration_date', $date);

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

        if (!$key || !preg_match($this->regex, $key)) {
            return $this->redirectToRoute('homepage', array(), 301);
        }

        $dm = $this->get('doctrine_mongodb')->getManager();
        $person = $dm->getRepository('PumukitSchemaBundle:Person')->findOneBy(array('properties.expiration_key' => new \MongoId($key)));

        $user = $this->get('security.context')->getToken()->getUser();
        if ($user->getEmail() == $person->getEmail()) {
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
                $mmObj->setPropertyAsDateTime('expiration_date', $date);

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
