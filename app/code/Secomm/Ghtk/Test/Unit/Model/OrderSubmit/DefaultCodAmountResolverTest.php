<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\OrderSubmit;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Item as ShipmentItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\OrderSubmit\DefaultCodAmountResolver;
use Secomm\ShippingCore\Api\Cod\CodPaymentMethodResolverInterface;

/**
 * TASK-6YG3HP (architecture v4 §4.1) — COD payment-method IDENTIFICATION is
 * ShippingCore-owned (`CodPaymentMethodResolverInterface::isCod`); the carrier
 * no longer reads any carrier-owned COD method config. What this resolver still
 * owns is the provider conversion: collect amount (base_total_due) → pick_money.
 */
class DefaultCodAmountResolverTest extends TestCase
{
    private CodPaymentMethodResolverInterface&MockObject $codResolver;
    private DefaultCodAmountResolver $sut;

    protected function setUp(): void
    {
        $this->codResolver = $this->createMock(CodPaymentMethodResolverInterface::class);
        $this->sut = new DefaultCodAmountResolver($this->codResolver);
    }

    /**
     * @param string[] $codMethods
     */
    private function resolverIdentifying(array $codMethods): void
    {
        $this->codResolver->method('isCod')->willReturnCallback(
            fn (string $method): bool => in_array($method, $codMethods, true)
        );
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

    public function testSharedResolverSaysNonCodCollectsNothing(): void
    {
        $this->resolverIdentifying(['cashondelivery']);
        $order = $this->order('mollie_methods_creditcard', 500000.0, []);
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getAllItems')->willReturn([]);

        $this->assertSame(0.0, $this->sut->resolve($order, $shipment));
    }

    public function testSharedResolverSaysCodCollectsOutstandingDue(): void
    {
        $this->resolverIdentifying(['cashondelivery', 'custom_cod']);
        $items = $this->orderItem(10, 2.0, 0.0);
        $order = $this->order('custom_cod', 500000.0, $items);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getAllItems')->willReturn([$this->shipmentItem(10, 2.0, $items)]);

        $this->assertSame(500000.0, $this->sut->resolve($order, $shipment));
    }

    public function testSharedResolverIsTheOnlyIdentificationSource(): void
    {
        // An unconfigured method (shared resolver → false) collects nothing even
        // if it LOOKS like COD — the carrier has no private list anymore.
        $this->resolverIdentifying([]); // empty ShippingCore COD config
        $order = $this->order('cashondelivery', 500000.0, []);
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getAllItems')->willReturn([]);

        $this->assertSame(0.0, $this->sut->resolve($order, $shipment));
    }

    public function testCodPartialShipmentFailsFast(): void
    {
        $this->resolverIdentifying(['cashondelivery']);
        $items = $this->orderItem(10, 2.0, 0.0); // 2 ordered, only 1 shipping now
        $order = $this->order('cashondelivery', 500000.0, $items);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getAllItems')->willReturn([$this->shipmentItem(10, 1.0, $items)]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Partial COD shipments are not supported');
        $this->sut->resolve($order, $shipment);
    }

    public function testCodFollowUpShipmentOfRemainingQtyIsNotPartial(): void
    {
        $this->resolverIdentifying(['cashondelivery']);
        // 2 ordered, 1 already shipped (previous saved shipment), 1 shipping now → full coverage.
        $items = $this->orderItem(10, 2.0, 1.0);
        $order = $this->order('cashondelivery', 500000.0, $items);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getAllItems')->willReturn([$this->shipmentItem(10, 1.0, $items)]);

        $this->assertSame(500000.0, $this->sut->resolve($order, $shipment));
    }

    public function testFullyPaidCodStyleOrderCollectsNothing(): void
    {
        $this->resolverIdentifying(['cashondelivery']);
        // COD method but deposit covered everything → nothing to collect at the door.
        $items = $this->orderItem(10, 1.0, 0.0);
        $order = $this->order('cashondelivery', 0.0, $items);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getAllItems')->willReturn([$this->shipmentItem(10, 1.0, $items)]);

        $this->assertSame(0.0, $this->sut->resolve($order, $shipment));
    }

    public function testDepositLeavesOnlyRemainingCollectible(): void
    {
        $this->resolverIdentifying(['cashondelivery']);
        $items = $this->orderItem(10, 1.0, 0.0);
        $order = $this->order('cashondelivery', 200000.0, $items);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getAllItems')->willReturn([$this->shipmentItem(10, 1.0, $items)]);

        $this->assertSame(200000.0, $this->sut->resolve($order, $shipment));
    }
}
