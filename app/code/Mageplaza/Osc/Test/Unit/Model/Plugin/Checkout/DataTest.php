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

namespace Mageplaza\Osc\Test\Unit\Model\Plugin\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Mageplaza\Osc\Helper\Data as OscData;
use Mageplaza\Osc\Model\Plugin\Checkout\Data;
use PHPUnit\Framework\MockObject\Matcher\InvokedCount as InvokedCountMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class Data
 *
 */
class DataTest extends TestCase
{
    /**
     * @var OscData|MockObject
     */
    private $helperMock;

    /**
     * @var Session|MockObject
     */
    private $checkoutSessionMock;

    /**
     * @var Data
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->helperMock = $this->getMockBuilder(OscData::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->checkoutSessionMock = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->plugin = new Data(
            $this->helperMock,
            $this->checkoutSessionMock
        );
    }

    public function testMethod()
    {
        $methods = get_class_methods(\Magento\Checkout\Helper\Data::class);

        $this->assertTrue(in_array('isAllowedGuestCheckout', $methods));
    }

    /**
     * @return array
     */
    public function providerTestAfterIsAllowedGuestCheckout()
    {
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();

        return [
            [
                false,
                $quoteMock,
                false,
                self::never()
            ],
            [
                false,
                false,
                false,
                self::never()
            ],
            [
                true,
                $quoteMock,
                true,
                self::once()
            ]
        ];
    }

    /**
     * @param boolean              $result
     * @param MockObject | boolean $quote
     * @param boolean              $isEnable
     * @param InvokedCountMatcher  $allowExpect
     *
     * @dataProvider providerTestAfterIsAllowedGuestCheckout
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testAfterIsAllowedGuestCheckout($result, $quote, $isEnable, $allowExpect)
    {
        /**
         * @var \Magento\Checkout\Helper\Data $subject
         */
        $subject = $this->getMockBuilder(\Magento\Checkout\Helper\Data::class)->disableOriginalConstructor()->getMock();
        $this->checkoutSessionMock->expects($this->once())->method('getQuote')->willReturn($quote);
        if ($quote) {
            $this->helperMock->expects($this->once())->method('isEnabled')->willReturn($isEnable);
        }

        $this->helperMock->expects($allowExpect)->method('getAllowGuestCheckout')->with($quote)->willReturn(true);

        $this->assertEquals(
            $result,
            $this->plugin->afterIsAllowedGuestCheckout($subject, $result)
        );
    }
}
