<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Tests\Command;

use Pumukit\CoreBundle\Tests\PumukitTestCase;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Services\FactoryService;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * @coversNothing
 */
class ExpiredVideoDeleteCommandTest extends PumukitTestCase
{
    private FactoryService $factoryService;
    private CommandTester $tester;

    public function setUp(): void
    {
        parent::setUp();
        $this->factoryService = self::getContainer()->get(FactoryService::class);
        $this->tester = new CommandTester(new Application(self::$kernel)->find('pumukit:expired:video:delete'));
    }

    public function testWithoutForceItOnlyListsTheVideos(): void
    {
        $series = $this->factoryService->createSeries();
        $expired = $this->multimediaObject($series, '-400 days');

        $this->assertSame(Command::FAILURE, $this->tester->execute([]));

        $this->assertStringContainsString($expired->getId(), $this->tester->getDisplay());
        $this->dm->clear();
        $this->assertNotNull($this->dm->getRepository(MultimediaObject::class)->find($expired->getId()));
    }

    public function testWithForceItDeletesTheVideosExpiredLongerThanTheConfiguredDays(): void
    {
        $series = $this->factoryService->createSeries();
        $expired = $this->multimediaObject($series, '-400 days');
        $recent = $this->multimediaObject($series, '-10 days');
        $seriesId = $series->getId();

        $this->assertSame(Command::SUCCESS, $this->tester->execute(['--force' => true]));

        $this->dm->clear();
        $repository = $this->dm->getRepository(MultimediaObject::class);
        $this->assertNull($repository->find($expired->getId()));
        $this->assertNotNull($repository->find($recent->getId()));
        $this->assertNotNull($this->dm->getRepository(Series::class)->find($seriesId));
    }

    public function testWithForceItDeletesTheSeriesLeftEmpty(): void
    {
        $series = $this->factoryService->createSeries();
        $this->multimediaObject($series, '-400 days');
        $seriesId = $series->getId();

        $this->assertSame(Command::SUCCESS, $this->tester->execute(['--force' => true]));

        $this->dm->clear();
        $this->assertNull($this->dm->getRepository(Series::class)->find($seriesId));
        $this->assertSame(0, $this->dm->getRepository(MultimediaObject::class)->count(['series' => $seriesId]));
    }

    private function multimediaObject(Series $series, string $expiration): MultimediaObject
    {
        $multimediaObject = $this->factoryService->createMultimediaObject($series);
        $multimediaObject->setPropertyAsDateTime('expiration_date', new \DateTime($expiration));
        $this->dm->flush();

        return $multimediaObject;
    }
}
