<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Tests\EventListener;

use Pumukit\CoreBundle\Tests\PumukitTestCase;
use Pumukit\SchemaBundle\Services\FactoryService;

/**
 * @internal
 *
 * @coversNothing
 */
class MultimediaObjectListenerTest extends PumukitTestCase
{
    public function testANewMultimediaObjectExpiresAfterTheConfiguredDays(): void
    {
        $factoryService = self::getContainer()->get(FactoryService::class);
        $multimediaObject = $factoryService->createMultimediaObject($factoryService->createSeries());

        $expiration = $multimediaObject->getPropertyAsDateTime('expiration_date');
        $this->assertEqualsWithDelta(new \DateTime('+365 days')->getTimestamp(), $expiration->getTimestamp(), 5);
        $this->assertSame([365], $multimediaObject->getProperty('renew_expiration_date'));
    }

    public function testTheCloneKeepsTheExpirationOfTheOriginal(): void
    {
        $factoryService = self::getContainer()->get(FactoryService::class);
        $multimediaObject = $factoryService->createMultimediaObject($factoryService->createSeries());
        $multimediaObject->setPropertyAsDateTime('expiration_date', new \DateTime('2031-01-01'));
        $this->dm->flush();

        $clone = $factoryService->cloneMultimediaObject($multimediaObject);

        $this->assertSame($multimediaObject->getProperty('expiration_date'), $clone->getProperty('expiration_date'));
    }
}
