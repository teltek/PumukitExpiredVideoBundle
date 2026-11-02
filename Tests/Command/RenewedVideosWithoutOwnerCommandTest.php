<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Tests\Command;

use Pumukit\CoreBundle\Tests\PumukitTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * @coversNothing
 */
class RenewedVideosWithoutOwnerCommandTest extends PumukitTestCase
{
    public function testForceRequiresAValidRenewDate(): void
    {
        $tester = new CommandTester(new Application(self::$kernel)->find('pumukit:expired:video:renewed:without:owner'));

        $this->assertSame(Command::INVALID, $tester->execute(['--force' => true]));
        $this->assertSame(Command::INVALID, $tester->execute(['--force' => true, '--renewDate' => '31-12-2030']));
        $this->assertStringContainsString('renewDate', $tester->getDisplay());
    }
}
