<?php

declare(strict_types=1);

namespace Pumukit\ExpiredVideoBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pumukit\ExpiredVideoBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @internal
 *
 * @coversNothing
 */
class ConfigurationTest extends TestCase
{
    public function testTheEmptyConfigurationGetsTheDefaults(): void
    {
        $config = $this->process([]);

        $this->assertTrue($config['enable']);
        $this->assertSame(365, $config['expiration_date_days']);
        $this->assertSame(90, $config['range_warning_days']);
    }

    public function testZeroDaysNoLongerDisablesTheBundle(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['expiration_date_days' => 0]);
    }

    public function testTheBundleIsDisabledWithEnable(): void
    {
        $this->assertFalse($this->process(['enable' => false])['enable']);
    }

    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
