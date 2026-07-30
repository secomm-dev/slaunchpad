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

namespace Mageplaza\Osc\Test\Unit\Observer;

use Magento\Checkout\Model\Session;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Mageplaza\Osc\Observer\QuoteSubmitBefore;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QuoteSubmitBeforeTest extends TestCase
{
    /**
     * @var Session|MockObject
     */
    private $checkoutSessionMock;

    /**
     * @var QuoteSubmitBefore
     */
    private $quoteSubmitBefore;

    /**
     * @var MockObject
     */
    private $orderMock;

    /**
     * @var MockObject
     */
    private $quoteMock;

    /**
     * @var Observer|MockObject
     */
    private $observerMock;

    protected function setUp(): void
    {
        $this->checkoutSessionMock = $this->getMockBuilder(Session::class)
            ->addMethods(['getOscData'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->observerMock = $this->getMockBuilder(Observer::class)
            ->disableOriginalConstructor()
            ->getMock();

        $eventMock = $this->getMockBuilder(Event::class)
            ->addMethods(['getOrder', 'getQuote'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->observerMock->expects($this->exactly(2))->method('getEvent')->willReturn($eventMock);
        $eventMock->expects($this->once())->method('getOrder')->willReturn($this->orderMock);
        $eventMock->expects($this->once())->method('getQuote')->willReturn($this->quoteMock);

        $this->quoteSubmitBefore = new QuoteSubmitBefore(
            $this->checkoutSessionMock
        );
    }

    public function testExecute()
    {
        $comment = 'test';
        $deliveryTime = '11/02/2020';
        $houseSecurityCode = '123';
        $giftWrapAmount = 10;
        $baseGiftWrapAmount = 10;
        $giftWrapType = 'test';
        $quoteItemId = 1;
        $this->checkoutSessionMock->expects($this->once())
            ->method('getOscData')
            ->willReturn(
                [
                    'comment' => $comment,
                    'deliveryTime' => $deliveryTime,
                    'houseSecurityCode' => $houseSecurityCode
                ]
            );

        $shippingAddressMock = $this->getMockBuilder(Address::class)
            ->onlyMethods(['hasData'])
            ->addMethods(['getUsedGiftWrap', 'getGiftWrapType', 'getOscGiftWrapAmount', 'getBaseOscGiftWrapAmount'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->quoteMock->expects($this->once())->method('getShippingAddress')->willReturn($shippingAddressMock);
        $shippingAddressMock->expects($this->once())->method('getUsedGiftWrap')->willReturn(true);
        $shippingAddressMock->expects($this->once())->method('hasData')->with('osc_gift_wrap_amount')->willReturn(true);
        $shippingAddressMock->expects($this->once())->method('getGiftWrapType')->willReturn($giftWrapType);
        $shippingAddressMock->expects($this->once())->method('getOscGiftWrapAmount')->willReturn($giftWrapAmount);
        $shippingAddressMock->expects($this->once())->method('getBaseOscGiftWrapAmount')
            ->willReturn($baseGiftWrapAmount);

        $orderSetDataCallCount = 0;
        $this->orderMock->expects($this->exactly(6))->method('setData')
            ->willReturnCallback(function ($key, $value) use (&$orderSetDataCallCount, $comment, $deliveryTime, $houseSecurityCode, $giftWrapType, $giftWrapAmount, $baseGiftWrapAmount) {
                $orderSetDataCallCount++;
                match ($orderSetDataCallCount) {
                    1 => $this->assertEquals(['osc_order_comment', $comment], [$key, $value]),
                    2 => $this->assertEquals(['osc_delivery_time', $deliveryTime], [$key, $value]),
                    3 => $this->assertEquals(['osc_order_house_security_code', $houseSecurityCode], [$key, $value]),
                    4 => $this->assertEquals(['gift_wrap_type', $giftWrapType], [$key, $value]),
                    5 => $this->assertEquals(['osc_gift_wrap_amount', $giftWrapAmount], [$key, $value]),
                    6 => $this->assertEquals(['base_osc_gift_wrap_amount', $baseGiftWrapAmount], [$key, $value]),
                };
                return $this->orderMock;
            });

        $quoteItemMock = $this->getMockBuilder(QuoteItem::class)
            ->onlyMethods(['hasData'])
            ->addMethods(['getOscGiftWrapAmount', 'getBaseOscGiftWrapAmount'])
            ->disableOriginalConstructor()
            ->getMock();

        $orderItemMock = $this->getMockBuilder(OrderItem::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderMock->expects($this->once())->method('getItems')->willReturn([$orderItemMock]);
        $orderItemMock->expects($this->once())->method('getQuoteItemId')->willReturn($quoteItemId);
        $this->quoteMock->expects($this->once())->method('getItemById')->with($quoteItemId)->willReturn($quoteItemMock);
        $quoteItemMock->expects($this->once())->method('hasData')->with('osc_gift_wrap_amount')->willReturn(true);
        $quoteItemMock->expects($this->once())->method('getOscGiftWrapAmount')->willReturn($giftWrapAmount);
        $quoteItemMock->expects($this->once())->method('getBaseOscGiftWrapAmount')->willReturn($baseGiftWrapAmount);

        $orderItemSetDataCallCount = 0;
        $orderItemMock->expects($this->exactly(2))->method('setData')
            ->willReturnCallback(function ($key, $value) use (&$orderItemSetDataCallCount, $giftWrapAmount, $baseGiftWrapAmount, $orderItemMock) {
                $orderItemSetDataCallCount++;
                match ($orderItemSetDataCallCount) {
                    1 => $this->assertEquals(['osc_gift_wrap_amount', $giftWrapAmount], [$key, $value]),
                    2 => $this->assertEquals(['base_osc_gift_wrap_amount', $baseGiftWrapAmount], [$key, $value]),
                };
                return $orderItemMock;
            });

        $this->quoteSubmitBefore->execute($this->observerMock);
    }
}
