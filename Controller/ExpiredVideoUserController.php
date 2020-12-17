<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Controller;

use Pumukit\ExpiredVideoBundle\Utils\TokenUtils;
use Pumukit\NewAdminBundle\Controller\NewAdminControllerInterface;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Person;
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
     * @Route("/renew/{key}/", name="pumukit_expired_video_renew",  defaults={"key": null})
     * @Template("PumukitExpiredVideoBundle:ExpiredVideo:renewExpiredVideo.html.twig")
     */
    public function renewMultimediaObjectFromEmailAction(string $key)
    {
        $expiredVideoConfigurationService = $this->container->get('pumukit_expired_video.configuration');
        $days = $expiredVideoConfigurationService->getExpirationDateDaysConf();

        if (!TokenUtils::isValidToken($key)) {
            return $this->redirectToRoute('homepage', [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $dm = $this->get('doctrine_mongodb.odm.document_manager');
        $mmObj = $dm->getRepository(MultimediaObject::class)->findOneBy(
            ['properties.expiration_key' => new \MongoId($key)]
        );

        $user = $this->getUser();
        $roleCode = $this->container->getParameter('pumukitschema.personal_scope_role_code');

        if ($mmObj) {
            $people = $mmObj->getPeopleByRoleCod($roleCode, true);
            $isOwner = false;
            if (isset($people) && !empty($people) && is_array($people)) {
                foreach ($people as $person) {
                    if ($person->getEmail() === $user->getEmail()) {
                        $isOwner = true;
                    }
                }
            }

            if ($isOwner) {
                $aRenew = $mmObj->getProperty(
                    $expiredVideoConfigurationService->getMultimediaObjectPropertyRenewExpirationDateKey()
                );
                $aRenew[] = $days;
                $mmObj->setProperty(
                    $expiredVideoConfigurationService->getMultimediaObjectPropertyRenewExpirationDateKey(),
                    $aRenew
                );

                $mmObj->removeProperty($expiredVideoConfigurationService->getMultimediaObjectPropertyRenewKey());

                $date = new \DateTime();
                $date->add(new \DateInterval('P'.$days.'D'));
                $mmObj->setPropertyAsDateTime(
                    $expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(),
                    $date
                );

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
    public function renewAllMultimediaObjectsFromEmailAction(string $key)
    {
        $expiredVideoConfigurationService = $this->container->get('pumukit_expired_video.configuration');
        $days = $expiredVideoConfigurationService->getExpirationDateDaysConf();

        if (!TokenUtils::isValidToken($key)) {
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
                    $aRenew = $mmObj->getProperty(
                        $expiredVideoConfigurationService->getMultimediaObjectPropertyRenewExpirationDateKey()
                    );
                    $aRenew[] = $days;
                    $mmObj->setProperty(
                        $expiredVideoConfigurationService->getMultimediaObjectPropertyRenewExpirationDateKey(),
                        $aRenew
                    );

                    $mmObj->removeProperty($expiredVideoConfigurationService->getMultimediaObjectPropertyRenewKey());

                    $date = new \DateTime();
                    $date->add(new \DateInterval('P'.$days.'D'));
                    $mmObj->setPropertyAsDateTime(
                        $expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(),
                        $date
                    );
                }

                $person->removeProperty(
                    $expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey()
                );
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
}
