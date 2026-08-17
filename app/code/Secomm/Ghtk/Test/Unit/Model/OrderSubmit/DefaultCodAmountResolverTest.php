<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\OrderSubmit;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Item as ShipmentItem;
use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\Ghtk\Model\OrderSubmit\DefaultCodAmountResolver;

class DefaultCodAmountResolverTest extends TestCase
{
    private function resolver(array $codCodes = ['cashondelivery']): DefaultCodAmountResolver
    {
        $config = $this->createMock(GhtkConfig::class);
        $config->method('getCodMethodCodes')->willReturn($codCodes);

        return new DefaultCodAmountResolver($config);
    }

    private function order(string $method, float $due, array $items): Order
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn($method);

        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getBaseTotalDue')->willReturn($due);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getAllItems')->willReturn($items);

        return $order;
    }

    /** @return Item[] */
    private function orderItem(int $id, float $qtyOrdered, float $qtyShipped): array
    {
        $item = $this->createMock(Item::class);
        $item->method('getItemId')->willReturn($id);
        $item->method('getIsVirtual')->willReturn(false);
        $item->method('getProductType')->willReturn('simple');
        $item->method('getParentItem')->willReturn(null);
        $item->method('getQtyOrdered')->willReturn($qtyOrdered);
        $item->method('getQtyShipped')->willReturn($qtyShipped);

        return [$item];
    }

    private function shipmentItem(int $orderItemId, float $qty, array $orderItems): ShipmentItem
    {
        $item = $this->createMock(ShipmentItem::class);
        $item->method('getOrderItemId')->willReturn($orderItemId);
        $item->method('getQty')->willReturn($qty);
        $item->method('getOrderItem')->willReturn($orderItems[$orderItemId - 1] ?? null);

        return $item;
    }

    public function testPrepaidOrderCollectsNothing(): void
    {
        $order = $this->order('mollie_methods_creditcard', 500000.0, []);
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getAllItems')->willReturn([]);

        $this->assertSame(0.0, $this->resolver()->resolve($order, $shipment));
    }

    public function testCodFullShipmentCollectsOutstandingDue(): void
    {
        $items = $this->orderItem(10, 2.0, 0.0);
        $order = $this->order('cashondelivery', 500000.0, $items);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getAllItems')->willReturn([$this->shipmentItem(10, 2.0, $items)]);

        $this->assertSame(500000.0, $this->resolver()->resolve($order, $shipment));
    }

    public function testCodPartialShipmentFailsFast(): void
    {
        $items = $this->orderItem(10, 2.0, 0.0); // 2 ordered, only 1 shipping now
        $order = $this->order('cashondelivery', 500000.0, $items);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getAllItems')->willReturn([$this->shipmentItem(10, 1.0, $items)]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Partial COD shipments are not supported');
        $this->resolver()->resolve($order, $shipment);
    }

    public function testCodFollowUpShipmentOfRemainingQtyIsNotPartial(): void
    {
        // 2 ordered, 1 already shipped (previous saved shipment), 1 shipping now → full coverage.
        $items = $this->orderItem(10, 2.0, 1.0);
        $order = $this->order('cashondelivery', 500000.0, $items);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getAllItems')->willReturn([$this->shipmentItem(10, 1.0, $items)]);

        $this->assertSame(500000.0, $this->resolver()->resolve($order, $shipment));
    }

    public function testFullyPaidCodStyleOrderCollectsNothing(): void
    {
        // COD-listed method but deposit covered everything → nothing to collect at the door.
        $items = $this->orderItem(10, 1.0, 0.0);
        $order = $this->order('cashondelivery', 0.0, $items);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getAllItems')->willReturn([$this->shipmentItem(10, 1.0, $items)]);

        $this->assertSame(0.0, $this->resolver()->resolve($order, $shipment));
    }

    public function testDepositLeavesOnlyRemainingCollectible(): void
    {
        $items = $this->orderItem(10, 1.0, 0.0);
        $order = $this->order('cashondelivery', 200000.0, $items);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getAllItems')->willReturn([$this->shipmentItem(10, 1.0, $items)]);

        $this->assertSame(200000.0, $this->resolver()->resolve($order, $shipment));
    }
}
