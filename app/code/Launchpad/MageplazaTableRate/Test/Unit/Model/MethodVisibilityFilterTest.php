<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001 §8) — per-method visibility filter tests.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Test\Unit\Model;

use Launchpad\MageplazaTableRate\Model\Data\MethodSettings;
use Launchpad\MageplazaTableRate\Model\MethodSettingsProvider;
use Launchpad\MageplazaTableRate\Model\MethodVisibilityFilter;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Shipping\Model\Rate\Result;
use PHPUnit\Framework\TestCase;

class MethodVisibilityFilterTest extends TestCase
{
    private MethodSettingsProvider $settingsProvider;

    private MethodVisibilityFilter $filter;

    protected function setUp(): void
    {
        // Only the settings map is stubbed — getHiddenMethodIds()/getFallbackMethodIds()
        // must run their REAL logic on top of it.
        $this->settingsProvider = $this->getMockBuilder(MethodSettingsProvider::class)
            ->onlyMethods(['getSettingsMap'])
            ->disableOriginalConstructor()->getMock();
        $this->filter = new MethodVisibilityFilter($this->settingsProvider);
    }

    private function methodRate(int $methodId, string $carrier = 'mptablerate'): Method
    {
        $rate = $this->getMockBuilder(Method::class)
            ->addMethods(['getCarrier', 'getMethod'])
            ->disableOriginalConstructor()
            ->getMock();
        $rate->method('getCarrier')->willReturn($carrier);
        $rate->method('getMethod')->willReturn((string) $methodId);

        return $rate;
    }

    /**
     * @param array<int, MethodSettings> $settingsMap
     */
    private function givenSettings(array $settingsMap): void
    {
        $this->settingsProvider->method('getSettingsMap')->willReturn($settingsMap);
    }

    private function makeResult(array $initial = []): Result
    {
        $rates = $initial;
        $result = $this->createMock(Result::class);
        $result->method('getAllRates')->willReturnCallback(static function () use (&$rates) {
            return $rates;
        });
        $result->method('append')->willReturnCallback(static function ($rate) use (&$rates) {
            $rates[] = $rate;

            return null;
        });
        $result->method('reset')->willReturnCallback(static function () use (&$rates) {
            $rates = [];

            return null;
        });

        return $result;
    }

    public function testVisibleOnlyStaysVisible(): void
    {
        $this->givenSettings([10 => new MethodSettings(10, true, false)]);
        $result = $this->makeResult([$this->methodRate(10)]);

        $this->filter->filter($result);
        $this->assertCount(1, $result->getAllRates());
    }

    public function testHiddenFallbackOnlyMethodIsRemoved(): void
    {
        $this->givenSettings([10 => new MethodSettings(10, false, true)]);
        $result = $this->makeResult([$this->methodRate(10)]);

        $this->filter->filter($result);
        $this->assertCount(0, $result->getAllRates());
    }

    public function testNeitherNorOtherCarriersTouched(): void
    {
        $ghn = $this->methodRate(0, 'secomm_ghn');
        $result = $this->makeResult([$ghn, $this->methodRate(20)]);

        // membership must NEVER hide realtime carrier methods (directive §10)
        $this->givenSettings([
            20 => new MethodSettings(20, false, true),
            30 => new MethodSettings(30, true, true),
        ]);

        $this->filter->filter($result);
        $rates = $result->getAllRates();
        $this->assertCount(1, $rates);
        $this->assertSame($ghn, $rates[0]);
    }

    public function testMethodWithoutSettingsRowStaysVisible(): void
    {
        $this->givenSettings([]); // no rows at all
        $result = $this->makeResult([$this->methodRate(10)]);

        $this->filter->filter($result);
        $this->assertCount(1, $result->getAllRates());
    }

    public function testMixedResultKeepsOnlyNonHidden(): void
    {
        $this->givenSettings([
            10 => new MethodSettings(10, false, true),   // fallback-only → hidden
            20 => new MethodSettings(20, true, true),    // both → visible
        ]);
        $result = $this->makeResult([$this->methodRate(10), $this->methodRate(20)]);

        $this->filter->filter($result);
        $rates = $result->getAllRates();
        $this->assertCount(1, $rates);
        $this->assertSame('20', (string) $rates[0]->getMethod());
    }

    public function testNoHiddenIsCheapNoOp(): void
    {
        $this->settingsProvider->method('getSettingsMap')->willReturn([10 => new MethodSettings(10, true, false)]);
        $rate = $this->methodRate(10);
        $result = $this->makeResult([$rate]);

        $this->filter->filter($result);
        $this->assertSame([$rate], $result->getAllRates());
    }
}
