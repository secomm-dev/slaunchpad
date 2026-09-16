<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Launchpad\MageplazaExtraFeeFix\Test\Unit\Plugin\Model\Order;

use Launchpad\MageplazaExtraFeeFix\Plugin\Model\Order\CanCreditmemoPlugin;
use Launchpad\MageplazaExtraFeeFix\Service\ExtraFeeOrderHelper;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CanCreditmemoPluginTest extends TestCase
{
    private ExtraFeeOrderHelper&MockObject $helper;
    private CanCreditmemoPlugin $plugin;

    protected function setUp(): void
    {
        $this->helper = $this->createMock(ExtraFeeOrderHelper::class);
        $this->plugin = new CanCreditmemoPlugin($this->helper);
    }

    public function testPassesThroughWhenResultIsFalse(): void
    {
        $order = $this->createOrderMock(0.0, 0.0, 0.0);
        $this->helper->expects($this->never())->method('hasNonRefundableExtraFee');

        $result = $this->plugin->afterCanCreditmemo($order, false);

        $this->assertFalse($result);
    }

    public function testPassesThroughWhenNoRefundYet(): void
    {
        $order = $this->createOrderMock(0.0, 0.0, 0.0);
        $this->helper->expects($this->never())->method('hasNonRefundableExtraFee');

        $result = $this->plugin->afterCanCreditmemo($order, true);

        $this->assertTrue($result);
    }

    public function testPassesThroughWhenNoNonRefundableFee(): void
    {
        $order = $this->createOrderMock(50000.0, 0.0, 0.0);
        $this->helper->method('hasNonRefundableExtraFee')->willReturn(false);

        $result = $this->plugin->afterCanCreditmemo($order, true);

        $this->assertTrue($result);
    }

    public function testPassesThroughWhenItemsNotFullyRefunded(): void
    {
        $order = $this->createOrderMock(50000.0, 0.0, 0.0);
        $this->helper->method('hasNonRefundableExtraFee')->willReturn(true);
        $this->helper->method('areAllItemsRefundedOrCanceled')->willReturn(false);

        $result = $this->plugin->afterCanCreditmemo($order, true);

        $this->assertTrue($result);
    }

    public function testBlocksCreditmemoWhenAllConditionsMet(): void
    {
        // All items refunded + non-refundable fee + shipping fully refunded
        $order = $this->createOrderMock(50000.0, 30000.0, 30000.0);
        $this->helper->method('hasNonRefundableExtraFee')->willReturn(true);
        $this->helper->method('areAllItemsRefundedOrCanceled')->willReturn(true);

        $result = $this->plugin->afterCanCreditmemo($order, true);

        $this->assertFalse($result);
    }

    public function testAllowsCreditmemoWhenShippingNotYetRefunded(): void
    {
        // All items refunded + non-refundable fee + shipping NOT refunded yet
        $order = $this->createOrderMock(50000.0, 30000.0, 0.0);
        $this->helper->method('hasNonRefundableExtraFee')->willReturn(true);
        $this->helper->method('areAllItemsRefundedOrCanceled')->willReturn(true);

        $result = $this->plugin->afterCanCreditmemo($order, true);

        $this->assertTrue($result);
    }

    public function testBlocksCreditmemoWhenFreeShipping(): void
    {
        // All items refunded + non-refundable fee + free shipping (both 0)
        $order = $this->createOrderMock(50000.0, 0.0, 0.0);
        $this->helper->method('hasNonRefundableExtraFee')->willReturn(true);
        $this->helper->method('areAllItemsRefundedOrCanceled')->willReturn(true);

        $result = $this->plugin->afterCanCreditmemo($order, true);

        $this->assertFalse($result);
    }

    // -----------------------------------------------------------------------

    private function createOrderMock(
        float $totalRefunded,
        float $shippingInvoiced,
        float $shippingRefunded
    ): Order&MockObject {
        $order = $this->createMock(Order::class);
        $order->method('getTotalRefunded')->willReturn($totalRefunded);
        $order->method('getBaseShippingInvoiced')->willReturn($shippingInvoiced);
        $order->method('getBaseShippingRefunded')->willReturn($shippingRefunded);
        return $order;
    }
}
