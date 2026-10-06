<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CurrencyPrecision\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\CurrencyPrecision\Model\PrecisionResolver;

/**
 * @covers \Secomm\CurrencyPrecision\Model\PrecisionResolver
 */
class PrecisionResolverTest extends TestCase
{
    private const STORE_ID = 1;

    private ScopeConfigInterface|MockObject $scopeConfig;
    private StoreManagerInterface|MockObject $storeManager;
    private PrecisionResolver $resolver;

    protected function setUp(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(self::STORE_ID);
        $store->method('getCurrentCurrencyCode')->willReturn('VND');

        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->scopeConfig->method('getValue')->willReturnCallback(
            function (string $path, string $scopeType, ?int $scopeCode) {
                $this->assertSame(ScopeInterface::SCOPE_STORE, $scopeType);
                $this->assertSame(self::STORE_ID, $scopeCode);

                return $path === PrecisionResolver::XML_PATH_PAIRS ? $this->pairs : $this->default;
            }
        );

        $this->resolver = new PrecisionResolver($this->scopeConfig, $this->storeManager);
    }

    private string $pairs = '';
    private string $default = 'auto';

    public function testReturnsNullWhenNoConfiguredPrecision(): void
    {
        $this->pairs = '';
        $this->default = 'auto';

        $this->assertNull($this->resolver->resolve('VND'));
        $this->assertNull($this->resolver->resolveCurrent());
    }

    public function testResolvesPerCurrencyFromPairs(): void
    {
        $this->pairs = 'VND=0, USD=2';
        $this->default = 'auto';

        $this->assertSame(0, $this->resolver->resolve('vnd'), 'lookup must be case-insensitive');
        $this->assertSame(2, $this->resolver->resolve('USD'));
        // Currency without override falls back to Auto even with pairs present.
        $this->assertNull($this->resolver->resolve('EUR'));
    }

    public function testFallsBackToDefaultPrecisionForUnlistedCurrency(): void
    {
        $this->pairs = 'VND=0';
        $this->default = '2';

        $this->assertSame(0, $this->resolver->resolve('VND'), 'override wins over default');
        $this->assertSame(2, $this->resolver->resolve('EUR'));
    }

    public function testMalformedPairsAreIgnored(): void
    {
        $this->pairs = 'VNDX=9, VND, usd=3';
        $this->default = 'auto';

        $this->assertNull($this->resolver->resolve('VNDX'), 'out-of-range precision must not apply');
        $this->assertSame(3, $this->resolver->resolve('USD'), 'valid pair inside a malformed list still applies');
    }

    public function testMemoizesConfigReadPerRequest(): void
    {
        $this->pairs = 'VND=0, USD=2';
        $this->default = 'auto';

        // Two resolves per currency on a hot path — config must be read once
        // per path total, not once per call.
        $this->scopeConfig->expects($this->exactly(2))->method('getValue');

        $this->resolver->resolve('VND');
        $this->resolver->resolve('VND');
        $this->resolver->resolve('USD');
        $this->resolver->resolve('USD');
        $this->resolver->resolveCurrent();
    }
}
