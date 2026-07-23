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

use Closure;
use Magento\Checkout\Api\Data\TotalsInformationInterface;
use Magento\Checkout\Model\TotalsInformationManagement as CoreTotalsInformationManagement;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\CartExtensionInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Mageplaza\Osc\Model\Plugin\Checkout\TotalsInformationManagement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TotalsInformationManagementTest extends TestCase
{
    /**
     * @var CartRepositoryInterface|MockObject
     */
    private $quoteRepositoryMock;

    /**
     * @var CartTotalRepositoryInterface|MockObject
     */
    private $cartTotalRepositoryMock;

    /**
     * @var TotalsInformationManagement
     */
    private $plugin;

    /**
     * @var CoreTotalsInformationManagement|MockObject
     */
    private $subjectMock;

    /**
     * @var Closure
     */
    private $closureMock;

    /**
     * @var TotalsInformationInterface|MockObject
     */
    private $totalsInformationMock;

    protected function setUp(): void
    {
        $this->quoteRepositoryMock = $this->getMockForAbstractClass(CartRepositoryInterface::class);
        $this->cartTotalRepositoryMock = $this->getMockForAbstractClass(CartTotalRepositoryInterface::class);

        $this->subjectMock = $this->getMockBuilder(CoreTotalsInformationManagement::class)
            ->disableOriginalConstructor()
            ->getMock();
        $subject = $this->subjectMock;
        $this->closureMock = function () use ($subject) {
            return $subject;
        };

        $this->totalsInformationMock = $this->getMockForAbstractClass(TotalsInformationInterface::class);

        $this->plugin = new TotalsInformationManagement(
            $this->quoteRepositoryMock,
            $this->cartTotalRepositoryMock
        );
    }

    public function testMethod()
    {
        $methods = get_class_methods(CoreTotalsInformationManagement::class);

        $this->assertTrue(in_array('calculate', $methods));
    }

    public function testAroundCalculateWithEmptyExtensionAttributes()
    {
        $cartId = 1;
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->quoteRepositoryMock->expects($this->once())
            ->method('get')
            ->with($cartId)
            ->willReturn($quoteMock);
        $quoteMock->expects($this->once())->method('getExtensionAttributes')->willReturn(null);

        $this->plugin->aroundCalculate($this->subjectMock, $this->closureMock, $cartId, $this->totalsInformationMock);
    }

    public function testAroundCalculateWithQuoteVirtual()
    {
        $cartId = 1;
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cartExtensionMock = $this->getMockForAbstractClass(CartExtensionInterface::class);
        $this->quoteRepositoryMock->expects($this->once())
            ->method('get')
            ->with($cartId)
            ->willReturn($quoteMock);
        $quoteMock->expects($this->once())->method('getExtensionAttributes')->willReturn($cartExtensionMock);
        $quoteMock->expects($this->once())->method('isVirtual')->willReturn(true);

        $this->plugin->aroundCalculate($this->subjectMock, $this->closureMock, $cartId, $this->totalsInformationMock);
    }

    public function testAroundCalculateWithEmptyShippingAssignments()
    {
        $cartId = 1;
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cartExtensionMock = $this->getMockBuilder(CartExtensionInterface::class)
            ->setMethods(['getShippingAssignments'])
            ->getMockForAbstractClass();
        $this->quoteRepositoryMock->expects($this->once())
            ->method('get')
            ->with($cartId)
            ->willReturn($quoteMock);
        $quoteMock->expects($this->once())->method('getExtensionAttributes')->willReturn($cartExtensionMock);
        $quoteMock->expects($this->once())->method('isVirtual')->willReturn(false);
        $cartExtensionMock->expects($this->once())->method('getShippingAssignments')->willReturn(null);

        $this->plugin->aroundCalculate($this->subjectMock, $this->closureMock, $cartId, $this->totalsInformationMock);
    }

    public function testAroundCalculate()
    {
        $cartId = 1;
        $shippingMethod = 'flatrate_flatrate';
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cartExtensionMock = $this->getMockBuilder(CartExtensionInterface::class)
            ->setMethods(['getShippingAssignments'])
            ->getMockForAbstractClass();
        $this->quoteRepositoryMock->expects($this->once())
            ->method('get')
            ->with($cartId)
            ->willReturn($quoteMock);
        $quoteMock->expects($this->once())->method('getExtensionAttributes')->willReturn($cartExtensionMock);
        $quoteMock->expects($this->once())->method('isVirtual')->willReturn(false);
        $shippingAssignmentsMock = $this->getMockForAbstractClass(ShippingAssignmentInterface::class);

        $cartExtensionMock->expects($this->exactly(2))
            ->method('getShippingAssignments')
            ->willReturn([$shippingAssignmentsMock]);
        $shippingMock = $this->getMockForAbstractClass(ShippingInterface::class);

        $shippingAssignmentsMock->expects($this->once())->method('getShipping')->willReturn($shippingMock);
        $shippingAddressMock = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMock->expects($this->once())->method('getShippingAddress')->willReturn($shippingAddressMock);
        $shippingAddressMock->expects($this->once())->method('getShippingMethod')->willReturn($shippingMethod);
        $shippingMock->expects($this->once())->method('setMethod')->with($shippingMethod);
        $this->quoteRepositoryMock->expects($this->once())
            ->method('save')
            ->with($quoteMock)
            ->willReturnSelf();

        $this->plugin->aroundCalculate($this->subjectMock, $this->closureMock, $cartId, $this->totalsInformationMock);
    }
}
