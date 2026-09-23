<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Cod;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\Cod\ConfiguredCodPaymentMethodResolver;

/**
 * TASK-STC3NB — configuration-backed COD payment identification:
 * exact match on a trimmed, comma-separated config list; empty/malformed config → safe false.
 */
class ConfiguredCodPaymentMethodResolverTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;

    private string $configValue = '';

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->scopeConfig->method('getValue')
            ->with(ConfiguredCodPaymentMethodResolver::CONFIG_PATH_PAYMENT_METHODS)
            ->willReturnCallback(fn (): ?string => $this->configValue === '' ? null : $this->configValue);
    }

    private function resolver(): ConfiguredCodPaymentMethodResolver
    {
        return new ConfiguredCodPaymentMethodResolver($this->scopeConfig);
    }

    public function testSingleConfiguredMethodIsCod(): void
    {
        $this->configValue = 'cashondelivery';

        $this->assertTrue($this->resolver()->isCod('cashondelivery'));
    }

    public function testEachOfMultipleConfiguredMethodsMatches(): void
    {
        $this->configValue = 'cashondelivery, custom_cod';

        $resolver = $this->resolver();
        $this->assertTrue($resolver->isCod('cashondelivery'));
        $this->assertTrue($resolver->isCod('custom_cod'));
    }

    public function testUnconfiguredMethodIsNotCod(): void
    {
        $this->configValue = 'cashondelivery, custom_cod';

        $this->assertFalse($this->resolver()->isCod('mollie'));
    }

    public function testEmptyConfigMeansNothingIsCod(): void
    {
        $this->configValue = '';

        $resolver = $this->resolver();
        $this->assertFalse($resolver->isCod('cashondelivery'));
        $this->assertFalse($resolver->isCod(''));
    }

    public function testNullConfigMeansNothingIsCod(): void
    {
        $this->configValue = '';

        $this->assertFalse($this->resolver()->isCod('cashondelivery'));
    }

    public function testSimilarAndPrefixCodesAreNotMatches(): void
    {
        $this->configValue = 'cashondelivery';

        $resolver = $this->resolver();
        $this->assertFalse($resolver->isCod('cashondel'));
        $this->assertFalse($resolver->isCod('cashondelivery_extra'));
        // Exact/case-sensitive: Magento payment codes are lowercase by convention.
        $this->assertFalse($resolver->isCod('CASHONDELIVERY'));
    }

    public function testConfigWhitespaceIsNormalizedDeterministically(): void
    {
        $this->configValue = '  cashondelivery ,   custom_cod  ,';

        $resolver = $this->resolver();
        $this->assertTrue($resolver->isCod('cashondelivery'));
        $this->assertTrue($resolver->isCod('custom_cod'));
        // Trailing junk is not trimmed away into a match — 'custom_codx' is a different code.
        $this->assertFalse($resolver->isCod('custom_codx'));
    }

    public function testQueriedCodeIsTrimmedBeforeComparison(): void
    {
        $this->configValue = 'cashondelivery';

        $this->assertTrue($this->resolver()->isCod(' cashondelivery '));
    }
}
