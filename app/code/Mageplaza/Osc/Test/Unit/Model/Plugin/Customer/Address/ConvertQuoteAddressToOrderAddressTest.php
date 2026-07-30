<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
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

namespace Mageplaza\Osc\Test\Unit\Model\Plugin\Customer\Address;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\ToOrderAddress;
use Mageplaza\Osc\Model\Plugin\Customer\Address\ConvertQuoteAddressToOrderAddress;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class ConvertQuoteAddressToOrderAddress
 *
 */
class ConvertQuoteAddressToOrderAddressTest extends TestCase
{
    /**
     * @var TimezoneInterface|MockObject
     */
    private $timezoneMock;

    /**
     * @var ConvertQuoteAddressToOrderAddress
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->timezoneMock = $this->getMockForAbstractClass(TimezoneInterface::class);
        $this->plugin = new ConvertQuoteAddressToOrderAddress($this->timezoneMock);
    }

    public function testMethod()
    {
        $methods = get_class_methods(ToOrderAddress::class);

        $this->assertTrue(in_array('convert', $methods));
    }

    public function testAroundConvert()
    {
        /**
         * @var ToOrderAddress $subject
         */
        $subject = $this->getMockBuilder(ToOrderAddress::class)->disableOriginalConstructor()->getMock();

        /**
         * @var Address $quoteAddressMock
         */
        $quoteAddressMock = $this->getMockBuilder(Address::class)
            ->onlyMethods(['getData'])
            ->addMethods(['getAddressType'])
            ->disableOriginalConstructor()->getMock();
        $orderAddressMock = $this->getMockBuilder(\Magento\Sales\Model\Order\Address::class)
            ->disableOriginalConstructor()
            ->getMock();

        $closureMock = function () use ($orderAddressMock) {
            return $orderAddressMock;
        };

        $quoteAddressMock->expects($this->exactly(3))
            ->method('getData')
            ->willReturnMap([
                ['mposc_field_1', null, 'test1'],
                ['mposc_field_2', null, 'test2'],
                ['mposc_field_3', null, 'test3'],
            ]);
        $quoteAddressMock->method('getAddressType')->willReturn('shipping');
        $setDataArgs = [];
        $orderAddressMock->expects($this->exactly(3))
            ->method('setData')
            ->willReturnCallback(function ($key, $value) use (&$setDataArgs, $orderAddressMock) {
                $setDataArgs[] = [$key, $value];
                return $orderAddressMock;
            });

        $this->plugin->aroundConvert($subject, $closureMock, $quoteAddressMock);

        $this->assertEquals([
            ['mposc_field_1', 'test1'],
            ['mposc_field_2', 'test2'],
            ['mposc_field_3', 'test3'],
        ], $setDataArgs);
    }
}
