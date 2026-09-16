<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Launchpad\MageplazaExtraFeeFix\Test\Unit\Plugin\Model\Order;

use Launchpad\MageplazaExtraFeeFix\Plugin\Model\Order\StateResolverPlugin;
use Launchpad\MageplazaExtraFeeFix\Service\ExtraFeeOrderHelper;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\OrderStateResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StateResolverPluginTest extends TestCase
{
    private ExtraFeeOrderHelper&MockObject $helper;
    private StateResolverPlugin $plugin;
    private OrderStateResolverInterface&MockObject $subject;

    protected function setUp(): void
    {
        $this->helper  = $this->createMock(ExtraFeeOrderHelper::class);
        $this->plugin  = new StateResolverPlugin($this->helper);
        $this->subject = $this->createMock(OrderStateResolverInterface::class);
    }

    public function testReturnsOriginalStateWhenNotComplete(): void
    {
        $order = $this->createOrderMock(50.0);
        $this->helper->expects($this->never())->method('hasNonRefundableExtraFee');

        $result = $this->plugin->afterGetStateForOrder(
            $this->subject,
            Order::STATE_PROCESSING,
            $order
        );

        $this->assertSame(Order::STATE_PROCESSING, $result);
    }

    public function testReturnsCompleteWhenNoRefund(): void
    {
        $order = $this->createOrderMock(0.0);
        $this->helper->expects($this->never())->method('hasNonRefundableExtraFee');

        $result = $this->plugin->afterGetStateForOrder(
            $this->subject,
            Order::STATE_COMPLETE,
            $order
        );

        $this->assertSame(Order::STATE_COMPLETE, $result);
    }

    public function testReturnsCompleteWhenNoNonRefundableFee(): void
    {
        $order = $this->createOrderMock(50000.0);
        $this->helper->method('hasNonRefundableExtraFee')->willReturn(false);

        $result = $this->plugin->afterGetStateForOrder(
            $this->subject,
            Order::STATE_COMPLETE,
            $order
        );

        $this->assertSame(Order::STATE_COMPLETE, $result);
    }

    public function testReturnsCompleteWhenItemsNotFullyRefunded(): void
    {
        $order = $this->createOrderMock(50000.0);
        $this->helper->method('hasNonRefundableExtraFee')->willReturn(true);
        $this->helper->method('areAllItemsRefundedOrCanceled')->willReturn(false);

        $result = $this->plugin->afterGetStateForOrder(
            $this->subject,
            Order::STATE_COMPLETE,
            $order
        );

        $this->assertSame(Order::STATE_COMPLETE, $result);
    }

    public function testReturnsClosedWhenNonRefundableFeeAndAllItemsDone(): void
    {
        $order = $this->createOrderMock(50000.0);
        $this->helper->method('hasNonRefundableExtraFee')->willReturn(true);
        $this->helper->method('areAllItemsRefundedOrCanceled')->willReturn(true);

        $result = $this->plugin->afterGetStateForOrder(
            $this->subject,
            Order::STATE_COMPLETE,
            $order
        );

        $this->assertSame(Order::STATE_CLOSED, $result);
    }

    // -----------------------------------------------------------------------

    private function createOrderMock(float $totalRefunded): Order&MockObject
    {
        $order = $this->createMock(Order::class);
        $order->method('getTotalRefunded')->willReturn($totalRefunded);
        return $order;
    }
}
