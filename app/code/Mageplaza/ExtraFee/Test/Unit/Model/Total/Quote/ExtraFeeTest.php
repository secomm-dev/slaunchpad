<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

declare(strict_types=1);

namespace Mageplaza\ExtraFee\Test\Unit\Model\Total\Quote;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\DataObject;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Mageplaza\ExtraFee\Helper\Data as HelperData;
use Mageplaza\ExtraFee\Model\Config\Source\CalculateOptions;
use Mageplaza\ExtraFee\Model\Total\Quote\ExtraFee;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the core quote extra-fee calculation helpers: option parsing,
 * percentage base composition (tax/discount/shipping), valid-amount detection,
 * rule fee-include resolution and customer-group resolution.
 *
 * The methods under test are protected/private business logic, exercised through
 * reflection while the heavy total-collector constructor is satisfied by ObjectManager.
 *
 * @covers \Mageplaza\ExtraFee\Model\Total\Quote\ExtraFee
 */
class ExtraFeeTest extends TestCase
{
    /** @var HelperData|MockObject */
    private $helper;

    /** @var CustomerSession|MockObject */
    private $customerSession;

    /** @var ExtraFee */
    private $model;

    protected function setUp(): void
    {
        $this->helper          = $this->createMock(HelperData::class);
        $this->customerSession = $this->createMock(CustomerSession::class);

        $this->model = (new ObjectManager($this))->getObject(ExtraFee::class, [
            'helper'          => $this->helper,
            'customerSession' => $this->customerSession,
        ]);
    }

    /**
     * Invoke a protected/private method on the SUT.
     *
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     */
    private function invoke(string $method, array $args)
    {
        $ref = new \ReflectionMethod(ExtraFee::class, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($this->model, $args);
    }

    /* ---------------------------------------------------------------------
     * calculateOptions(): config string -> array of ids
     * ------------------------------------------------------------------- */

    /**
     * Happy path: a comma separated config string is split into individual ids.
     */
    public function testCalculateOptionsSplitsCommaSeparatedString(): void
    {
        $this->assertSame(['1', '2', '3'], $this->invoke('calculateOptions', ['1,2,3']));
    }

    /**
     * Edge case: empty string yields an empty option list.
     */
    public function testCalculateOptionsReturnsEmptyArrayForEmptyString(): void
    {
        $this->assertSame([], $this->invoke('calculateOptions', ['']));
    }

    /**
     * Edge case: null (no config saved) yields an empty option list.
     */
    public function testCalculateOptionsReturnsEmptyArrayForNull(): void
    {
        $this->assertSame([], $this->invoke('calculateOptions', [null]));
    }

    /* ---------------------------------------------------------------------
     * calculateOptionsFee(): builds the base a percentage fee is applied on
     * ------------------------------------------------------------------- */

    /**
     * Happy path: with no calculate-options the base subtotal passes through untouched.
     */
    public function testCalculateOptionsFeeReturnsBaseWhenNoOptions(): void
    {
        $quote = new DataObject(['base_subtotal_with_discount' => 100]);

        $result = $this->invoke('calculateOptionsFee', [
            $quote, 100.0, 100.0, [], 15.0, 120.0, 90.0, 10.0,
        ]);

        $this->assertSame(100.0, $result);
    }

    /**
     * Happy path: SHIPPING_FEE adds the (excl-tax) shipping amount to the base.
     */
    public function testCalculateOptionsFeeAddsShipping(): void
    {
        $quote = new DataObject(['base_subtotal_with_discount' => 100]);

        $result = $this->invoke('calculateOptionsFee', [
            $quote, 100.0, 100.0, [CalculateOptions::SHIPPING_FEE], 15.0, 120.0, 90.0, 10.0,
        ]);

        $this->assertSame(110.0, $result);
    }

    /**
     * Happy path: TAX switches the base to the tax-inclusive total and uses incl-tax shipping.
     */
    public function testCalculateOptionsFeeUsesInclTaxTotalsWhenTaxSelected(): void
    {
        $quote = new DataObject(['base_subtotal_with_discount' => 100]);

        // TAX -> base becomes totalInclTax (120); SHIPPING uses baseShippingInclTax (15) => 135
        $result = $this->invoke('calculateOptionsFee', [
            $quote, 100.0, 100.0, [CalculateOptions::TAX, CalculateOptions::SHIPPING_FEE], 15.0, 120.0, 90.0, 10.0,
        ]);

        $this->assertSame(135.0, $result);
    }

    /**
     * Branch: DISCOUNT with a non-zero subtotal-with-discount adjusts by (withDiscount - exclTax).
     */
    public function testCalculateOptionsFeeAppliesDiscountWithNonZeroSubtotal(): void
    {
        $quote = new DataObject(['base_subtotal_with_discount' => 90]);

        // 100 + (90 - 100) = 90
        $result = $this->invoke('calculateOptionsFee', [
            $quote, 100.0, 100.0, [CalculateOptions::DISCOUNT], 15.0, 120.0, 90.0, 10.0,
        ]);

        $this->assertSame(90.0, $result);
    }

    /**
     * Edge case: DISCOUNT with a zero subtotal-with-discount adjusts by (withDiscount - totalInclTax).
     */
    public function testCalculateOptionsFeeAppliesDiscountWithZeroSubtotal(): void
    {
        $quote = new DataObject(['base_subtotal_with_discount' => 0]);

        // 100 + (90 - 120) = 70
        $result = $this->invoke('calculateOptionsFee', [
            $quote, 100.0, 100.0, [CalculateOptions::DISCOUNT], 15.0, 120.0, 90.0, 10.0,
        ]);

        $this->assertSame(70.0, $result);
    }

    /* ---------------------------------------------------------------------
     * hasValidFeeAmount(): is there at least one positive calculated fee?
     * ------------------------------------------------------------------- */

    /**
     * Happy path: an option with a positive excl-tax amount is considered valid.
     */
    public function testHasValidFeeAmountTrueForPositiveAmount(): void
    {
        $this->assertTrue($this->invoke('hasValidFeeAmount', [[['calculated_amount' => 5]]]));
    }

    /**
     * Happy path: a positive incl-tax amount alone is enough to be valid.
     */
    public function testHasValidFeeAmountTrueForPositiveInclTaxAmount(): void
    {
        $this->assertTrue($this->invoke('hasValidFeeAmount', [[['calculated_amount_incl_tax' => 3]]]));
    }

    /**
     * Edge case: all-zero amounts are not valid.
     */
    public function testHasValidFeeAmountFalseForZeroAmounts(): void
    {
        $this->assertFalse($this->invoke('hasValidFeeAmount', [[['calculated_amount' => 0, 'calculated_amount_incl_tax' => 0]]]));
    }

    /**
     * Edge case: missing keys default to 0 and an empty list are both invalid.
     */
    public function testHasValidFeeAmountFalseForMissingKeysAndEmptyList(): void
    {
        $this->assertFalse($this->invoke('hasValidFeeAmount', [[[]]]));
        $this->assertFalse($this->invoke('hasValidFeeAmount', [[]]));
    }

    /* ---------------------------------------------------------------------
     * checkCalculateOptions(): rule override vs global config
     * ------------------------------------------------------------------- */

    /**
     * Happy path: with no rule override the global "calculate_options" config is used.
     */
    public function testCheckCalculateOptionsUsesGlobalConfigByDefault(): void
    {
        $this->helper->method('getConfigGeneral')->with('calculate_options')->willReturn('1,2');
        $rule = new DataObject(); // no fee_include

        $this->assertSame(['1', '2'], $this->invoke('checkCalculateOptions', [$rule]));
    }

    /**
     * Branch: a rule with its own fee_include (not "mp-use-config") overrides the global config.
     */
    public function testCheckCalculateOptionsUsesRuleOverride(): void
    {
        $this->helper->method('getConfigGeneral')->willReturn('1,2');
        $rule = new DataObject(['fee_include' => '3']);

        $this->assertSame(['3'], $this->invoke('checkCalculateOptions', [$rule]));
    }

    /**
     * Edge case: rule fee_include = "mp-use-config" falls back to the global config.
     */
    public function testCheckCalculateOptionsFallsBackWhenRuleUsesConfig(): void
    {
        $this->helper->method('getConfigGeneral')->willReturn('2');
        $rule = new DataObject(['fee_include' => 'mp-use-config']);

        $this->assertSame(['2'], $this->invoke('checkCalculateOptions', [$rule]));
    }

    /* ---------------------------------------------------------------------
     * getCustomerGroupId(): logged-in / backend session / guest fallback
     * ------------------------------------------------------------------- */

    /**
     * Happy path: a logged-in customer returns the front-end session group id.
     */
    public function testGetCustomerGroupIdForLoggedInCustomer(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->customerSession->method('getCustomerGroupId')->willReturn(7);

        $this->assertSame(7, $this->invoke('getCustomerGroupId', [new DataObject()]));
    }

    /**
     * Branch: a guest with an active backend (admin order) session uses that group id.
     */
    public function testGetCustomerGroupIdFromBackendSession(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(false);
        $backendSession = new DataObject(['id' => 99, 'customer_group_id' => 4]);

        $this->assertSame(4, $this->invoke('getCustomerGroupId', [$backendSession]));
    }

    /**
     * Edge case: a pure guest (no backend session) falls back to group 0.
     */
    public function testGetCustomerGroupIdDefaultsToZeroForGuest(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(false);

        $this->assertSame(0, $this->invoke('getCustomerGroupId', [new DataObject()]));
    }
}
