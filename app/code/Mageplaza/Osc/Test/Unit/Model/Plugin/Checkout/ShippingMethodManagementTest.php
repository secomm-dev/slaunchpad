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

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\EstimateAddressInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\ShippingMethodManagement as CoreShippingMethodManagement;
use Mageplaza\Osc\Model\Plugin\Checkout\ShippingMethodManagement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class ShippingMethodManagementTest extends TestCase
{
    /**
     * @var CartRepositoryInterface|MockObject
     */
    private $quoteRepositoryMock;

    /**
     * @var AddressRepositoryInterface|MockObject
     */
    private $addressRepositoryMock;

    /**
     * @var ShippingMethodManagement
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->quoteRepositoryMock = $this->getMockForAbstractClass(CartRepositoryInterface::class);
        $this->addressRepositoryMock = $this->getMockForAbstractClass(AddressRepositoryInterface::class);
        $this->plugin = new ShippingMethodManagement(
            $this->quoteRepositoryMock,
            $this->addressRepositoryMock
        );
    }

    public function testMethod()
    {
        $methods = get_class_methods(CoreShippingMethodManagement::class);

        $this->assertTrue(in_array('estimateByAddress', $methods));
        $this->assertTrue(in_array('estimateByExtendedAddress', $methods));
        $this->assertTrue(in_array('estimateByAddressId', $methods));
    }

    /**
     * @return array
     */
    public function providerTestAroundEstimateByAddress()
    {
        return [
            ['aroundEstimateByAddress', EstimateAddressInterface::class, true],
            ['aroundEstimateByExtendedAddress', AddressInterface::class, false]
        ];
    }

    /**
     * @param EstimateAddressInterface|AddressInterface $class
     * @param string                                    $method
     * @param boolean                                   $isAdditionalMethod
     *
     * @dataProvider providerTestAroundEstimateByAddress
     * @throws       NoSuchEntityException
     * @throws       ReflectionException
     */
    public function testAroundEstimateByAddress($method, $class, $isAdditionalMethod)
    {
        /**
         * @var CoreShippingMethodManagement $subject
         */
        $subject = $this->getMockBuilder(CoreShippingMethodManagement::class)
            ->disableOriginalConstructor()
            ->getMock();
        $closureMock = function () use ($subject) {
            return $subject;
        };

        $cartId = 1;
        $estimateAddressMethods = get_class_methods($class);
        if ($isAdditionalMethod) {
            $estimateAddressMethods[] = 'getStreet';
            $estimateAddressMethods[] = 'getCity';
            $estimateAddressMethods[] = 'getId';
        }

        /**
         * @var EstimateAddressInterface $estimateAddressMock
         */
        $estimateAddressMock = $this->getMockBuilder($class)
            ->setMethods($estimateAddressMethods)
            ->getMockForAbstractClass();
        $this->mockSaveAddress($estimateAddressMock);

        $this->plugin->{$method}($subject, $closureMock, $cartId, $estimateAddressMock);
    }

    /**
     * @return array
     */
    public function providerTestAroundEstimateWithQuoteVirtual()
    {
        return [
            ['aroundEstimateByAddress', EstimateAddressInterface::class],
            ['aroundEstimateByExtendedAddress', AddressInterface::class]
        ];
    }

    /**
     * @param EstimateAddressInterface|AddressInterface $class
     * @param string                                    $method
     *
     * @dataProvider providerTestAroundEstimateWithQuoteVirtual
     * @throws       NoSuchEntityException
     * @throws       ReflectionException
     */
    public function testAroundEstimateWithQuoteVirtual($method, $class)
    {
        /**
         * @var CoreShippingMethodManagement $subject
         */
        $subject = $this->getMockBuilder(CoreShippingMethodManagement::class)
            ->disableOriginalConstructor()
            ->getMock();
        $closureMock = function () use ($subject) {
            return $subject;
        };

        $cartId = 1;

        /**
         * @var EstimateAddressInterface $estimateAddressMock
         */
        $estimateAddressMock = $this->getMockForAbstractClass($class);
        $this->mockSaveAddressWithVirtual();

        $this->plugin->{$method}($subject, $closureMock, $cartId, $estimateAddressMock);
    }

    /**
     * @throws LocalizedException
     * @throws ReflectionException
     */
    public function testAroundEstimateByAddressId()
    {
        $addressId = 1;
        $cartId = 1;
        /**
         * @var CoreShippingMethodManagement $subject
         */
        $subject = $this->getMockBuilder(CoreShippingMethodManagement::class)
            ->disableOriginalConstructor()
            ->getMock();
        $closureMock = function () use ($subject) {
            return $subject;
        };

        $addressMock = $this->getMockForAbstractClass(\Magento\Customer\Api\Data\AddressInterface::class);
        $this->addressRepositoryMock->expects($this->once())
            ->method('getById')
            ->with($addressId)
            ->willReturn($addressMock);
        $this->mockSaveAddress($addressMock);

        $this->plugin->aroundEstimateByAddressId($subject, $closureMock, $cartId, $addressId);
    }

    /**
     * @throws LocalizedException
     * @throws ReflectionException
     */
    public function testAroundEstimateByAddressIdWithQuoteVirtual()
    {
        $addressId = 1;
        $cartId = 1;
        /**
         * @var CoreShippingMethodManagement $subject
         */
        $subject = $this->getMockBuilder(CoreShippingMethodManagement::class)
            ->disableOriginalConstructor()
            ->getMock();
        $closureMock = function () use ($subject) {
            return $subject;
        };

        $addressMock = $this->getMockForAbstractClass(\Magento\Customer\Api\Data\AddressInterface::class);
        $this->addressRepositoryMock->expects($this->once())
            ->method('getById')
            ->with($addressId)
            ->willReturn($addressMock);
        $this->mockSaveAddressWithVirtual();

        $this->plugin->aroundEstimateByAddressId($subject, $closureMock, $cartId, $addressId);
    }

    public function mockSaveAddressWithVirtual()
    {
        $cartId = 1;
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->quoteRepositoryMock->expects($this->once())->method('getActive')->with($cartId)->willReturn($quoteMock);
        $quoteMock->expects($this->once())->method('isVirtual')->willReturn(true);
    }

    /**
     * @param EstimateAddressInterface|MockObject $addressMock
     */
    public function mockSaveAddress($addressMock)
    {
        $cartId = 1;
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->quoteRepositoryMock->expects($this->once())->method('getActive')->with($cartId)->willReturn($quoteMock);
        $quoteMock->expects($this->once())->method('isVirtual')->willReturn(false);
        $addressMock->expects($this->once())->method('getCountryId')->willReturn('US');
        $addressMock->expects($this->once())->method('getPostcode')->willReturn('12345');
        $addressMock->expects($this->once())->method('getRegionId')->willReturn('region id');
        $addressMock->expects($this->once())->method('getStreet')->willReturn('street');
        $addressMock->expects($this->once())->method('getCity')->willReturn('city');
        $addressMock->expects($this->once())->method('getId')->willReturn(1);

        $shippingAddressMock = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMock->expects($this->once())->method('getShippingAddress')->willReturn($shippingAddressMock);
        $addressData = [
            AddressInterface::KEY_COUNTRY_ID => 'US',
            AddressInterface::KEY_POSTCODE => '12345',
            AddressInterface::KEY_REGION_ID => 'region id',
            AddressInterface::KEY_STREET => 'street',
            AddressInterface::KEY_CITY => 'city',
            AddressInterface::CUSTOMER_ADDRESS_ID => 1
        ];

        $shippingAddressMock->expects($this->once())->method('addData')->with($addressData)->willReturnSelf();
        $shippingAddressMock->expects($this->once())->method('save')->willReturnSelf();

        $this->quoteRepositoryMock->expects($this->once())->method('save')
            ->with($quoteMock);
    }
}
