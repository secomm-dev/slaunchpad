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

namespace Mageplaza\ExtraFee\Test\Unit\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Mageplaza\ExtraFee\Helper\Data as HelperData;
use Mageplaza\ExtraFee\Observer\ConvertQuoteToOrder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for the ConvertQuoteToOrder observer which copies the quote extra-fee data
 * onto the order during sales_model_service_quote_submit_before.
 *
 * @covers \Mageplaza\ExtraFee\Observer\ConvertQuoteToOrder
 */
class ConvertQuoteToOrderTest extends TestCase
{
    /** @var HelperData|MockObject */
    private $helper;

    /** @var ConvertQuoteToOrder */
    private $observerModel;

    protected function setUp(): void
    {
        $this->helper        = $this->createMock(HelperData::class);
        $this->observerModel = new ConvertQuoteToOrder($this->helper);
    }

    /**
     * Happy path: the observer reads order+quote off the event, persists the extra fee
     * onto the order items and clears the stored note from the checkout session.
     */
    public function testExecuteTransfersExtraFeeAndClearsNote(): void
    {
        $order = $this->createMock(Order::class);
        $quote = $this->createMock(Quote::class);

        // Event::getOrder()/getQuote() are magic accessors -> wrap payload in a DataObject.
        $event    = new DataObject(['order' => $order, 'quote' => $quote]);
        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        $checkoutSession = $this->createMock(CheckoutSession::class);
        $this->helper->method('getCheckoutSession')->willReturn($checkoutSession);

        $this->helper->expects($this->once())
            ->method('setExtraFeeForItems')
            ->with($order, $quote);

        $this->assertSame($this->observerModel, $this->observerModel->execute($observer));
    }
}
