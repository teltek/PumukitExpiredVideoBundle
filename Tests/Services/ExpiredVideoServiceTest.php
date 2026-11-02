<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Tests\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use PHPUnit\Framework\TestCase;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoConfigurationService;
use Pumukit\ExpiredVideoBundle\Services\ExpiredVideoService;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @internal
 *
 * @coversNothing
 */
class ExpiredVideoServiceTest extends TestCase
{
    public function testTheRenewalOfAUserWithoutUnlimitedPermissionUsesTheConfiguredDays(): void
    {
        $date = $this->service(false)->getExpirationDateByPermission();

        $this->assertEqualsWithDelta(new \DateTime('+365 days')->getTimestamp(), $date->getTimestamp(), 5);
    }

    public function testTheRenewalOfAUserWithUnlimitedPermissionHasNoLimit(): void
    {
        $this->assertSame('9999', $this->service(true)->getExpirationDateByPermission()->format('Y'));
    }

    private function service(bool $unlimited): ExpiredVideoService
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($unlimited);

        return new ExpiredVideoService(
            $this->createStub(DocumentManager::class),
            $authorizationChecker,
            new ExpiredVideoConfigurationService(365, 90, 'subject', 'template', 'subject', 'template', [], 'subject', 'template'),
        );
    }
}
