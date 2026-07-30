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

namespace Mageplaza\Osc\Test\Unit\Block\Order\View;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Mageplaza\Osc\Block\Order\View\CustomField;
use Mageplaza\Osc\Helper\Data;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CustomFieldTest extends TestCase
{
    /**
     * @var Registry|MockObject
     */
    protected $coreRegistryMock;

    /**
     * @var Data|MockObject
     */
    protected $helperMock;

    /**
     * @var CustomField
     */
    private $customFieldBlock;

    public function setUp(): void
    {
        /**
         * @var Context|MockObject $contextMock
         */
        $contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->coreRegistryMock = $this->getMockBuilder(Registry::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->helperMock = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->customFieldBlock = new CustomField(
            $contextMock,
            $this->coreRegistryMock,
            $this->helperMock
        );
    }

    public function testGetAddressDataWithEmptyOrder()
    {
        $this->coreRegistryMock->expects($this->once())
            ->method('registry')
            ->with('current_order')
            ->willReturn(null);

        $this->assertEquals([], $this->customFieldBlock->getAddressData());
    }

    public function testGetAddressDataWithBillingAddress()
    {
        $orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()->getMock();
        $this->coreRegistryMock->expects($this->once())
            ->method('registry')
            ->with('current_order')
            ->willReturn($orderMock);

        $billingAddressMock = $this->getMockBuilder(OrderAddressInterface::class)
            ->addMethods(['getData'])
            ->getMockForAbstractClass();
        $billingGetDataCallCount = 0;
        $billingAddressMock->expects($this->exactly(3))
            ->method('getData')
            ->willReturnCallback(function ($key) use (&$billingGetDataCallCount) {
                $billingGetDataCallCount++;
                return match ($billingGetDataCallCount) {
                    1 => (function () use ($key) { $this->assertEquals('mposc_field_1', $key); return 'value1'; })(),
                    2 => (function () use ($key) { $this->assertEquals('mposc_field_2', $key); return 'value2'; })(),
                    3 => (function () use ($key) { $this->assertEquals('mposc_field_3', $key); return '05/26/2020'; })(),
                };
            });

        $orderMock->expects($this->once())->method('getBillingAddress')->willReturn($billingAddressMock);
        $labelCallCount = 0;
        $this->helperMock->expects($this->exactly(3))
            ->method('getCustomFieldLabel')
            ->willReturnCallback(function ($index) use (&$labelCallCount) {
                $labelCallCount++;
                return match ($labelCallCount) {
                    1 => (function () use ($index) { $this->assertEquals(1, $index); return 'Label1'; })(),
                    2 => (function () use ($index) { $this->assertEquals(2, $index); return 'Label2'; })(),
                    3 => (function () use ($index) { $this->assertEquals(3, $index); return 'Label3'; })(),
                };
            });

        $result['billing'] = [
            'label' => __('Billing Address'),
            'value' => [
                [
                    'label' => 'Label1',
                    'value' => 'value1'
                ],
                [
                    'label' => 'Label2',
                    'value' => 'value2'
                ],
                [
                    'label' => 'Label3',
                    'value' => 'May 26, 2020'
                ]
            ],
        ];

        $this->assertEquals($result, $this->customFieldBlock->getAddressData());
    }

    public function testGetAddressData()
    {
        $orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()->getMock();
        $this->coreRegistryMock->expects($this->once())
            ->method('registry')
            ->with('current_order')
            ->willReturn($orderMock);

        $billingAddressMock = $this->getMockBuilder(OrderAddressInterface::class)
            ->addMethods(['getData'])
            ->getMockForAbstractClass();
        $billingGetDataCallCount = 0;
        $billingAddressMock->expects($this->exactly(3))
            ->method('getData')
            ->willReturnCallback(function ($key) use (&$billingGetDataCallCount) {
                $billingGetDataCallCount++;
                return match ($billingGetDataCallCount) {
                    1 => (function () use ($key) { $this->assertEquals('mposc_field_1', $key); return 'value1'; })(),
                    2 => (function () use ($key) { $this->assertEquals('mposc_field_2', $key); return 'value2'; })(),
                    3 => (function () use ($key) { $this->assertEquals('mposc_field_3', $key); return '05/26/2020'; })(),
                };
            });

        $orderMock->expects($this->once())->method('getBillingAddress')->willReturn($billingAddressMock);
        $labelCallCount = 0;
        $this->helperMock->expects($this->exactly(6))
            ->method('getCustomFieldLabel')
            ->willReturnCallback(function ($index) use (&$labelCallCount) {
                $labelCallCount++;
                return match ($labelCallCount) {
                    1 => (function () use ($index) { $this->assertEquals(1, $index); return 'Label1'; })(),
                    2 => (function () use ($index) { $this->assertEquals(2, $index); return 'Label2'; })(),
                    3 => (function () use ($index) { $this->assertEquals(3, $index); return 'Label3'; })(),
                    4 => (function () use ($index) { $this->assertEquals(1, $index); return 'Label1'; })(),
                    5 => (function () use ($index) { $this->assertEquals(2, $index); return 'Label2'; })(),
                    6 => (function () use ($index) { $this->assertEquals(3, $index); return 'Label3'; })(),
                };
            });

        $shippingAddressMock = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->getMock();

        $shippingGetDataCallCount = 0;
        $shippingAddressMock->expects($this->exactly(3))
            ->method('getData')
            ->willReturnCallback(function ($key) use (&$shippingGetDataCallCount) {
                $shippingGetDataCallCount++;
                return match ($shippingGetDataCallCount) {
                    1 => (function () use ($key) { $this->assertEquals('mposc_field_1', $key); return 'value1'; })(),
                    2 => (function () use ($key) { $this->assertEquals('mposc_field_2', $key); return 'value2'; })(),
                    3 => (function () use ($key) { $this->assertEquals('mposc_field_3', $key); return '05/26/2020'; })(),
                };
            });

        $orderMock->expects($this->once())->method('getShippingAddress')->willReturn($shippingAddressMock);

        $result['billing'] = [
            'label' => __('Billing Address'),
            'value' => [
                [
                    'label' => 'Label1',
                    'value' => 'value1'
                ],
                [
                    'label' => 'Label2',
                    'value' => 'value2'
                ],
                [
                    'label' => 'Label3',
                    'value' => 'May 26, 2020'
                ]
            ],
        ];

        $result['shipping'] = [
            'label' => __('Shipping Address'),
            'value' => [
                [
                    'label' => 'Label1',
                    'value' => 'value1'
                ],
                [
                    'label' => 'Label2',
                    'value' => 'value2'
                ],
                [
                    'label' => 'Label3',
                    'value' => 'May 26, 2020'
                ]
            ],
        ];

        $this->assertEquals($result, $this->customFieldBlock->getAddressData());
    }

    public function testGetAddressDataWithShippingAddress()
    {
        $orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()->getMock();
        $this->coreRegistryMock->expects($this->once())
            ->method('registry')
            ->with('current_order')
            ->willReturn($orderMock);

        $shippingAddressMock = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->getMock();

        $shippingGetDataCallCount = 0;
        $shippingAddressMock->expects($this->exactly(3))
            ->method('getData')
            ->willReturnCallback(function ($key) use (&$shippingGetDataCallCount) {
                $shippingGetDataCallCount++;
                return match ($shippingGetDataCallCount) {
                    1 => (function () use ($key) { $this->assertEquals('mposc_field_1', $key); return 'value1'; })(),
                    2 => (function () use ($key) { $this->assertEquals('mposc_field_2', $key); return 'value2'; })(),
                    3 => (function () use ($key) { $this->assertEquals('mposc_field_3', $key); return '05/26/2020'; })(),
                };
            });

        $orderMock->expects($this->once())->method('getShippingAddress')->willReturn($shippingAddressMock);
        $labelCallCount = 0;
        $this->helperMock->expects($this->exactly(3))
            ->method('getCustomFieldLabel')
            ->willReturnCallback(function ($index) use (&$labelCallCount) {
                $labelCallCount++;
                return match ($labelCallCount) {
                    1 => (function () use ($index) { $this->assertEquals(1, $index); return 'Label1'; })(),
                    2 => (function () use ($index) { $this->assertEquals(2, $index); return 'Label2'; })(),
                    3 => (function () use ($index) { $this->assertEquals(3, $index); return 'Label3'; })(),
                };
            });

        $result['shipping'] = [
            'label' => __('Shipping Address'),
            'value' => [
                [
                    'label' => 'Label1',
                    'value' => 'value1'
                ],
                [
                    'label' => 'Label2',
                    'value' => 'value2'
                ],
                [
                    'label' => 'Label3',
                    'value' => 'May 26, 2020'
                ]
            ],
        ];

        $this->assertEquals($result, $this->customFieldBlock->getAddressData());
    }
}
