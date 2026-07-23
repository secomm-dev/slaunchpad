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
 * @category  Mageplaza
 * @package   Mageplaza_Osc
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Test\Unit\Osc\Model\Plugin\Quote;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Mageplaza\Osc\Model\Plugin\Quote\QuoteValidator;
use PHPUnit\Framework\MockObject\Matcher\InvokedCount as InvokedCountMatcher;
use PHPUnit\Framework\TestCase;

class QuoteValidatorTest extends TestCase
{
    /**
     * @var QuoteValidator
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->plugin = new QuoteValidator();
    }

    public function testMethod()
    {
        $methods = get_class_methods(\Magento\Quote\Model\QuoteValidator::class);

        $this->assertTrue(in_array('validateBeforeSubmit', $methods));
    }

    /**
     * @return array
     */
    public function providerTestBeforeValidateBeforeSubmit()
    {
        return [
            [self::once(), false],
            [self::never(), true]
        ];
    }

    /**
     * @param InvokedCountMatcher $isVirtualExpect
     * @param boolean             $isVirtual
     *
     * @dataProvider providerTestBeforeValidateBeforeSubmit
     */
    public function testBeforeValidateBeforeSubmit($isVirtualExpect, $isVirtual)
    {
        /**
         * @var \Magento\Quote\Model\QuoteValidator $subject
         */
        $subject = $this->getMockBuilder(\Magento\Quote\Model\QuoteValidator::class)
            ->disableOriginalConstructor()->getMock();

        /**
         * @var Quote $quoteMock
         */
        $quoteMock = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->getMock();
        $quoteMock->expects($this->once())->method('isVirtual')->willReturn($isVirtual);
        $shippingAddressMock = $this->getMockBuilder(Address::class)
            ->setMethods(['setShouldIgnoreValidation'])
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMock->expects($isVirtualExpect)->method('getShippingAddress')->willReturn($shippingAddressMock);

        $billingAddressMock = $this->getMockBuilder(Address::class)
            ->setMethods(['setShouldIgnoreValidation'])
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMock->expects($this->once())->method('getBillingAddress')->willReturn($billingAddressMock);
        $billingAddressMock->expects($this->once())->method('setShouldIgnoreValidation')->with(true);

        $this->assertEquals([$quoteMock], $this->plugin->beforeValidateBeforeSubmit($subject, $quoteMock));
    }
}
