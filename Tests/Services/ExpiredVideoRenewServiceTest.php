<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Tests\Services;

use MongoDB\BSON\ObjectId;
use Pumukit\CoreBundle\Tests\PumukitTestCase;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoRenewService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Person;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Services\FactoryService;

/**
 * @internal
 *
 * @coversNothing
 */
class ExpiredVideoRenewServiceTest extends PumukitTestCase
{
    private ExpiredVideoRenewService $renewService;
    private FactoryService $factoryService;
    private Role $owner;

    public function setUp(): void
    {
        parent::setUp();
        $this->renewService = self::getContainer()->get(ExpiredVideoRenewService::class);
        $this->factoryService = self::getContainer()->get(FactoryService::class);

        $this->owner = new Role();
        $this->owner->setCod('owner');
        $this->dm->persist($this->owner);
        $this->dm->flush();
    }

    public function testTheRenewalOfAllTheVideosFindsThemAndConsumesTheToken(): void
    {
        $token = new ObjectId();
        $person = new Person();
        $person->setEmail('teacher@pumukit.local');
        $person->setProperty('expiration_key', $token);
        $this->dm->persist($person);

        $user = new User();
        $user->setUsername('teacher');
        $user->setEmail('teacher@pumukit.local');
        $user->setPassword('unused');
        $user->setPerson($person);
        $this->dm->persist($user);

        $multimediaObject = $this->factoryService->createMultimediaObject($this->factoryService->createSeries());
        $multimediaObject->addPersonWithRole($person, $this->owner);
        $multimediaObject->setPropertyAsDateTime('expiration_date', new \DateTime('+5 days'));
        $multimediaObject->setProperty('expiration_key', new ObjectId());
        $this->dm->flush();

        $found = $this->renewService->findMultimediaObjectsByPerson($person);
        $this->assertCount(1, $found);

        $this->renewService->renewAllMultimediaObjects($found, $user, $person);

        $this->dm->clear();
        $person = $this->dm->getRepository(Person::class)->find($person->getId());
        $this->assertNull($person->getProperty('expiration_key'));
        $this->assertNull($this->renewService->getPersonWithRenewKey((string) $token));

        $multimediaObject = $this->dm->getRepository(MultimediaObject::class)->find($multimediaObject->getId());
        $this->assertGreaterThan(new \DateTime('+300 days'), $multimediaObject->getPropertyAsDateTime('expiration_date'));
        $this->assertNull($multimediaObject->getProperty('expiration_key'));
    }
}
