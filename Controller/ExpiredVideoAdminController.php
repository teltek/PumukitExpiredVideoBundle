<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\CoreBundle\Services\PaginationService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoConfigurationService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoDeleteService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoUpdateService;
use Pumukit\NewAdminBundle\Controller\NewAdminControllerInterface;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Role;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/expired/video/system')]
#[IsGranted('ROLE_ACCESS_EXPIRED_VIDEO')]
class ExpiredVideoAdminController extends AbstractController implements NewAdminControllerInterface
{
    public const CSRF_TOKEN = 'expired_video_action';
    private const PER_PAGE = 50;

    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly ExpiredVideoConfigurationService $expiredVideoConfigurationService,
        private readonly ExpiredVideoDeleteService $expiredVideoDeleteService,
        private readonly ExpiredVideoUpdateService $expiredVideoUpdateService,
        private readonly PaginationService $paginationService,
        private readonly string $personalScopeRoleCode,
    ) {}

    #[Route('/list/', name: 'pumukit_expired_video_list', methods: ['GET'])]
    public function listAction(Request $request): Response
    {
        $date = new \DateTime('+'.$this->expiredVideoConfigurationService->getRangeWarningDays().' days');
        $key = $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(true);

        $queryBuilder = $this->documentManager->getRepository(MultimediaObject::class)->createQueryBuilder()
            ->field('status')->notEqual(MultimediaObject::STATUS_PROTOTYPE)
            ->field($key)->lte($date->format('c'))
            ->sort($key, -1)
        ;

        return $this->render('@PumukitExpiredVideo/ExpiredVideo/list.html.twig', [
            'ownerRol' => $this->documentManager->getRepository(Role::class)->findOneBy(['cod' => $this->personalScopeRoleCode]),
            'multimediaObjects' => $this->paginationService->createDoctrineODMMongoDBAdapter($queryBuilder, max(1, $request->query->getInt('page', 1)), self::PER_PAGE),
            'now' => new \DateTime()->format('c'),
        ]);
    }

    #[Route('/delete/{id}/', name: 'pumukit_expired_video_delete', methods: ['POST'])]
    public function deleteVideoAction(Request $request, MultimediaObject $multimediaObject): RedirectResponse
    {
        $this->checkToken($request);

        $this->expiredVideoDeleteService->removeMultimediaObject($multimediaObject);
        $this->addFlash('success', 'The video has been deleted.');

        return $this->redirectToList($request);
    }

    #[Route('/renew/{id}/', name: 'pumukit_expired_video_update', methods: ['POST'])]
    public function renewExpiredVideoAdminAction(Request $request, MultimediaObject $multimediaObject): RedirectResponse
    {
        $this->checkToken($request);

        $days = $this->expiredVideoConfigurationService->getExpirationDateDaysConf();
        $expiredOwnerCode = $this->expiredVideoConfigurationService->getRoleCodeExpiredOwner();
        $roleOwner = $this->documentManager->getRepository(Role::class)->findOneBy(['cod' => $this->personalScopeRoleCode]);
        foreach ($multimediaObject->getRoles() as $role) {
            if ($expiredOwnerCode !== $role->getCod()) {
                continue;
            }
            foreach ($multimediaObject->getPeopleByRoleCod($expiredOwnerCode, true) as $person) {
                $multimediaObject->addPersonWithRole($person, $roleOwner);
                $multimediaObject->removePersonWithRole($person, $role);
            }
        }

        $renewals = $multimediaObject->getProperty($this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewExpirationDateKey()) ?? [];
        $renewals[] = $days;
        $multimediaObject->setProperty($this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewExpirationDateKey(), $renewals);
        $multimediaObject->removeProperty($this->expiredVideoConfigurationService->getMultimediaObjectPropertyRenewKey());
        $multimediaObject->setPropertyAsDateTime(
            $this->expiredVideoConfigurationService->getMultimediaObjectPropertyExpirationDateKey(),
            new \DateTime('+'.$days.' days')
        );

        $this->expiredVideoUpdateService->postVideo($multimediaObject);
        $this->documentManager->flush();
        $this->addFlash('success', 'The video has been renewed.');

        return $this->redirectToList($request);
    }

    private function checkToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
    }

    private function redirectToList(Request $request): RedirectResponse
    {
        return $this->redirectToRoute('pumukit_expired_video_list', ['page' => max(1, $request->request->getInt('page', 1))], Response::HTTP_SEE_OTHER);
    }
}
