<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Tests\Command;

use Pumukit\CoreBundle\Tests\PumukitTestCase;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Person;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\SchemaBundle\Services\FactoryService;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * @coversNothing
 */
class ExpiredVideoUpdateCommandTest extends PumukitTestCase
{
    public function testTheOwnersOfAnExpiredVideoBecomeOldOwnersEvenWithoutTheRoleCreated(): void
    {
        $owner = new Role();
        $owner->setCod('owner');
        $this->dm->persist($owner);
        $tag = new Tag();
        $tag->setCod('PUCHWEBTV');
        $this->dm->persist($tag);
        $person = new Person();
        $person->setEmail('teacher@pumukit.local');
        $this->dm->persist($person);

        $factoryService = self::getContainer()->get(FactoryService::class);
        $multimediaObject = $factoryService->createMultimediaObject($factoryService->createSeries());
        $multimediaObject->addPersonWithRole($person, $owner);
        $multimediaObject->setPropertyAsDateTime('expiration_date', new \DateTime('-1 day'));
        $this->dm->flush();

        $tester = new CommandTester(new Application(self::$kernel)->find('pumukit:expired:video:update'));
        $this->assertSame(Command::SUCCESS, $tester->execute(['--force' => true]));

        $this->dm->clear();
        $role = $this->dm->getRepository(Role::class)->findOneBy(['cod' => 'expired_owner']);
        $this->assertInstanceOf(Role::class, $role);
        $updated = $this->dm->getRepository(MultimediaObject::class)->find($multimediaObject->getId());
        $this->assertSame([], $updated->getPeopleByRoleCod('owner', true));
        $this->assertCount(1, $updated->getPeopleByRoleCod('expired_owner', true));
    }
}
