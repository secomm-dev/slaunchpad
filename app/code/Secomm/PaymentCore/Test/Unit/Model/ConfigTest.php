<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PaymentCore\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\PaymentCore\Model\Config;

/**
 * FEAT-CSWYEJ / TASK-M20PT6 — config resolver: managed methods, expiry override
 * parsing, defaults (SPEC-FEAT-CSWYEJ §4.6, DEC D4).
 */
class ConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;

    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig, null);
    }

    public function testManagedMethods ParsesCommaList(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_MANAGED_METHODS, ScopeInterface::SCOPE_WEBSITE, null)
            ->willReturn('vnpay, mollie , ');
        $this->assertSame(['vnpay', 'mollie'], $this->config->getManagedMethods());
    }

    public function testResolveExpiryMinutes PrefersOverrideOverDefault(): void
    {
        $map = [
            [Config::XML_PATH_EXPIRY_OVERRIDES, ScopeInterface::SCOPE_WEBSITE, null, "vnpay:30\nmollie:60"],
            [Config::XML_PATH_DEFAULT_EXPIRY, ScopeInterface::SCOPE_WEBSITE, null, '120'],
        ];
        $this->scopeConfig->method('getValue')->willReturnMap($map);
        $this->assertSame(30, $this->config->resolveExpiryMinutes('vnpay'));
        $this->assertSame(60, $this->config->resolveExpiryMinutes('mollie'));
        $this->assertSame(120, $this->config->resolveExpiryMinutes('braintree'));
    }

    public function testResolveExpiryMinutes FallsBackToConstantWhenConfigEmpty(): void
    {
        $map = [
            [Config::XML_PATH_EXPIRY_OVERRIDES, ScopeInterface::SCOPE_WEBSITE, null, null],
            [Config::XML_PATH_DEFAULT_EXPIRY, ScopeInterface::SCOPE_WEBSITE, null, null],
        ];
        $this->scopeConfig->method('getValue')->willReturnMap($map);
        $this->assertSame(Config::DEFAULT_EXPIRY_MINUTES, $this->config->resolveExpiryMinutes('vnpay'));
    }

    /**
     * Invalid override lines are skipped, valid ones survive.
     */
    public function testExpiryOverrides IgnoresInvalidLines(): void
    {
        $this->scopeConfig->method('getValue')->willReturn("vnpay:30\nbad-line\nother:abc\n:45\nmollie:60\n0:10");
        $overrides = $this->config->getExpiryOverrides();
        $this->assertSame(['vnpay' => 30, 'mollie' => 60], $overrides);
    }

    public function testIsContinueDisabled MatchesExactCode(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('vnpay,mollie');
        $this->assertTrue($this->config->isContinueDisabled('vnpay'));
        $this->assertFalse($this->config->isContinueDisabled('braintree'));
    }

    public function testCronSettings FallBackToDefaults(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);
        $this->assertSame(Config::DEFAULT_BATCH_SIZE, $this->config->getBatchSize());
        $this->assertSame(Config::DEFAULT_FORCE_CLOSE_DAYS, $this->config->getForceCloseDays());
    }
}
