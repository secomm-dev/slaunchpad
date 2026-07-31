<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Plugin\Model\Checks;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\Checks\TotalMinMax;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Gateway\Helper\Rate;
use Secomm\ZaloPay\Plugin\Model\Checks\TotalMinMaxPlugin;

/**
 * Unit test for TotalMinMaxPlugin around-isApplicable logic.
 *
 * Covers the failure modes that previously hid ZaloPay from checkout:
 * - No conversion attempt when min/max are empty (skips missing currency rate).
 * - Graceful fallback when currency conversion throws (no exchange rate configured).
 */
class TotalMinMaxPluginTest extends TestCase
{
    /**
     * @var Rate|\PHPUnit\Framework\MockObject\MockObject
     */
    private $rate;

    /**
     * @var TotalMinMax|\PHPUnit\Framework\MockObject\MockObject
     */
    private $subject;

    /**
     * @var TotalMinMaxPlugin
     */
    private TotalMinMaxPlugin $plugin;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->rate = $this->createMock(Rate::class);
        $this->subject = $this->createMock(TotalMinMax::class);
        $this->plugin = new TotalMinMaxPlugin($this->rate);
    }

    /**
     * Non-ZaloPay methods delegate to the original check unchanged.
     *
     * @return void
     */
    public function testNonZaloPayMethodDelegatesToProceed(): void
    {
        $method = $this->createMock(MethodInterface::class);
        $quote = $this->createMock(Quote::class);

        $method->method('getCode')->willReturn('checkmo');
        $this->rate->expects($this->never())->method('getVndAmountByCurrency');

        $this->assertTrue(
            $this->plugin->aroundIsApplicable($this->subject, fn () => true, $method, $quote)
        );
    }

    /**
     * When neither min nor max is configured, the method is allowed without
     * any currency conversion — avoids breaking on a missing USD->VND rate.
     *
     * @return void
     */
    public function testZaloPayAllowedWhenMinMaxEmpty(): void
    {
        $method = $this->buildZaloPayMethod('', '');
        $quote = $this->createMock(Quote::class);

        $this->rate->expects($this->never())->method('getVndAmountByCurrency');

        $this->assertTrue(
            $this->plugin->aroundIsApplicable($this->subject, fn () => true, $method, $quote)
        );
    }

    /**
     * When conversion throws (no rate), the method is still allowed instead of
     * crashing and disappearing from checkout.
     *
     * @return void
     */
    public function testZaloPayAllowedWhenConversionFails(): void
    {
        $method = $this->buildZaloPayMethod('1000', '');
        $quote = $this->buildQuote('USD', 100.0);

        $this->rate->method('getVndAmountByCurrency')
            ->willThrowException(new LocalizedException(__('no rate')));

        $this->assertTrue(
            $this->plugin->aroundIsApplicable($this->subject, fn () => true, $method, $quote)
        );
    }

    /**
     * Amount below configured minimum is rejected.
     *
     * @return void
     */
    public function testZaloPayRejectedBelowMinimum(): void
    {
        $method = $this->buildZaloPayMethod('100000', '');
        $quote = $this->buildQuote('USD', 10.0);

        $this->rate->method('getVndAmountByCurrency')->willReturn(50000.0);

        $this->assertFalse(
            $this->plugin->aroundIsApplicable($this->subject, fn () => true, $method, $quote)
        );
    }

    /**
     * Amount within range is allowed.
     *
     * @return void
     */
    public function testZaloPayAllowedWithinRange(): void
    {
        $method = $this->buildZaloPayMethod('1000', '5000000');
        $quote = $this->buildQuote('USD', 100.0);

        $this->rate->method('getVndAmountByCurrency')->willReturn(2480000.0);

        $this->assertTrue(
            $this->plugin->aroundIsApplicable($this->subject, fn () => true, $method, $quote)
        );
    }

    /**
     * Build a Quote with base currency + total set via DataObject (magic getters).
     *
     * @param string $currency
     * @param float $total
     * @return Quote
     */
    private function buildQuote(string $currency, float $total): Quote
    {
        $quote = $this->createMock(Quote::class);
        // Quote getters (getBaseCurrencyCode/getBaseGrandTotal) are magic; seed DataObject.
        $quote->setData('base_currency_code', $currency);
        $quote->setData('base_grand_total', $total);

        return $quote;
    }

    /**
     * Build a mocked ZaloPay method with min/max config values.
     *
     * @param string $min
     * @param string $max
     * @return MethodInterface
     */
    private function buildZaloPayMethod(string $min, string $max): MethodInterface
    {
        $method = $this->createMock(MethodInterface::class);
        $method->method('getCode')->willReturn('zalopay');
        $map = [
            ['min_order_total', null, $min],
            ['max_order_total', null, $max],
        ];
        $method->method('getConfigData')->willReturnMap($map);

        return $method;
    }
}
