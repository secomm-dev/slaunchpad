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
            ->setMethods(['getData'])
            ->getMockForAbstractClass();
        $billingAddressMock->expects($this->exactly(3))
            ->method('getData')
            ->withConsecutive(['mposc_field_1'], ['mposc_field_2'], ['mposc_field_3'])
            ->willReturnOnConsecutiveCalls('value1', 'value2', '05/26/2020');

        $orderMock->expects($this->once())->method('getBillingAddress')->willReturn($billingAddressMock);
        $this->helperMock->expects($this->exactly(3))
            ->method('getCustomFieldLabel')
            ->withConsecutive([1], [2], [3])
            ->willReturnOnConsecutiveCalls('Label1', 'Label2', 'Label3');

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
            ->setMethods(['getData'])
            ->getMockForAbstractClass();
        $billingAddressMock->expects($this->exactly(3))
            ->method('getData')
            ->withConsecutive(['mposc_field_1'], ['mposc_field_2'], ['mposc_field_3'])
            ->willReturnOnConsecutiveCalls('value1', 'value2', '05/26/2020');

        $orderMock->expects($this->once())->method('getBillingAddress')->willReturn($billingAddressMock);
        $this->helperMock->expects($this->exactly(6))
            ->method('getCustomFieldLabel')
            ->withConsecutive([1], [2], [3], [1], [2], [3])
            ->willReturnOnConsecutiveCalls('Label1', 'Label2', 'Label3', 'Label1', 'Label2', 'Label3');

        $shippingAddressMock = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->getMock();

        $shippingAddressMock->expects($this->exactly(3))
            ->method('getData')
            ->withConsecutive(['mposc_field_1'], ['mposc_field_2'], ['mposc_field_3'])
            ->willReturnOnConsecutiveCalls('value1', 'value2', '05/26/2020');

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

        $shippingAddressMock->expects($this->exactly(3))
            ->method('getData')
            ->withConsecutive(['mposc_field_1'], ['mposc_field_2'], ['mposc_field_3'])
            ->willReturnOnConsecutiveCalls('value1', 'value2', '05/26/2020');

        $orderMock->expects($this->once())->method('getShippingAddress')->willReturn($shippingAddressMock);
        $this->helperMock->expects($this->exactly(3))
            ->method('getCustomFieldLabel')
            ->withConsecutive([1], [2], [3])
            ->willReturnOnConsecutiveCalls('Label1', 'Label2', 'Label3');

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
