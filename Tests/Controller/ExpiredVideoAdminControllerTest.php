<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Tests\Controller;

use Pumukit\CoreBundle\Tests\PumukitTestCase;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\PermissionProfile;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Services\FactoryService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 *
 * @coversNothing
 */
class ExpiredVideoAdminControllerTest extends PumukitTestCase
{
    private KernelBrowser $client;
    private FactoryService $factoryService;

    public function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
        $this->dm = self::getContainer()->get('doctrine_mongodb.odm.document_manager');
        $this->clearBBDD();
        $this->factoryService = self::getContainer()->get(FactoryService::class);

        $role = new Role();
        $role->setCod('owner');
        $this->dm->persist($role);
        $tag = new Tag();
        $tag->setCod('PUCHWEBTV');
        $this->dm->persist($tag);
        $this->dm->flush();
    }

    public function testTheListShowsTheVideosAboutToExpire(): void
    {
        $soon = $this->multimediaObject('+10 days');
        $later = $this->multimediaObject('+200 days');
        $this->login(['ROLE_SUPER_ADMIN']);

        $crawler = $this->client->request('GET', '/admin/expired/video/system/list/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($soon->getId(), $crawler->filter('tbody')->text());
        $this->assertStringNotContainsString($later->getId(), $crawler->filter('tbody')->text());
    }

    public function testTheListNeedsThePermission(): void
    {
        $this->login([PermissionProfile::SCOPE_PERSONAL]);

        $this->client->request('GET', '/admin/expired/video/system/list/');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAnExpiredVideoIsDeletedOnlyByPostWithAValidToken(): void
    {
        $expired = $this->multimediaObject('-1 day');
        $id = $expired->getId();
        $this->login(['ROLE_SUPER_ADMIN']);
        $crawler = $this->client->request('GET', '/admin/expired/video/system/list/');
        $form = $crawler->filter('form[action$="/delete/'.$id.'/"]')->form();

        $this->client->request('GET', '/admin/expired/video/system/delete/'.$id.'/');
        $this->assertResponseStatusCodeSame(405);

        $this->client->request('POST', '/admin/expired/video/system/delete/'.$id.'/', ['_token' => 'nope']);
        $this->assertResponseStatusCodeSame(403);

        $this->client->submit($form);
        $this->assertResponseRedirects('/admin/expired/video/system/list/?page=1', 303);

        $this->dm->clear();
        $this->assertNull($this->dm->getRepository(MultimediaObject::class)->find($id));
    }

    public function testAnExpiredVideoIsRenewedAndPublishedAgain(): void
    {
        $expired = $this->multimediaObject('-1 day');
        $this->login(['ROLE_SUPER_ADMIN']);
        $crawler = $this->client->request('GET', '/admin/expired/video/system/list/');

        $this->client->submit($crawler->filter('form[action$="/renew/'.$expired->getId().'/"]')->form());

        $this->assertResponseRedirects();
        $this->dm->clear();
        $renewed = $this->dm->getRepository(MultimediaObject::class)->find($expired->getId());
        $this->assertGreaterThan(new \DateTime('+300 days'), $renewed->getPropertyAsDateTime('expiration_date'));
        $this->assertTrue($renewed->containsTagWithCod('PUCHWEBTV'));
    }

    public function testTheExpirationDateIsUpdatedFromTheModal(): void
    {
        $multimediaObject = $this->multimediaObject('+10 days');
        $this->login(['ROLE_SUPER_ADMIN']);

        $this->client->request('POST', '/admin/expired/video/update/date/'.$multimediaObject->getId(), ['date' => 'tomorrow']);
        $this->assertResponseStatusCodeSame(400);

        $this->client->request('POST', '/admin/expired/video/update/date/'.$multimediaObject->getId(), ['date' => '2031-05-06']);
        $this->assertResponseRedirects();

        $this->dm->clear();
        $updated = $this->dm->getRepository(MultimediaObject::class)->find($multimediaObject->getId());
        $this->assertSame('2031-05-06', $updated->getPropertyAsDateTime('expiration_date')->format('Y-m-d'));
    }

    private function multimediaObject(string $expiration): MultimediaObject
    {
        $multimediaObject = $this->factoryService->createMultimediaObject($this->factoryService->createSeries());
        $multimediaObject->setPropertyAsDateTime('expiration_date', new \DateTime($expiration));
        $this->dm->flush();

        return $multimediaObject;
    }

    private function login(array $roles): void
    {
        $user = new User();
        $user->setUsername('admin-'.uniqid());
        $user->setEmail(uniqid().'@pumukit.local');
        $user->setPassword('unused');
        $user->setEnabled(true);
        $user->setRoles($roles);
        $this->dm->persist($user);
        $this->dm->flush();

        $this->client->loginUser($user, 'pumukit');
    }
}
