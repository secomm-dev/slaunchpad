<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Cod\Test\Unit\Model;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Item as ShipmentItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Cod\Api\CodCollectionLedgerInterface;
use Secomm\Cod\Api\CodPaymentMethodResolverInterface;
use Secomm\Cod\Model\CodCollectionAttempt;
use Secomm\Cod\Model\CodCollectionDecision;
use Secomm\Cod\Model\SingleCollectionCodResolver;

/**
 * TASK-DFGFZ9 phase 3 (DEC-TASKDFGFZ9-003) — P1 policy: ledger-owned frozen replay and the
 * cross-carrier one-collection rule (a caller CANNOT bypass by omitting the attempt — null
 * still runs the prior check). Then identification → currency → amount → partial.
 */
class SingleCollectionCodResolverTest extends TestCase
{
    private CodPaymentMethodResolverInterface&MockObject $identification;

    private CodCollectionLedgerInterface&MockObject $ledger;

    private SingleCollectionCodResolver $resolver;

    /** Ledger answers, configured per test (PHPUnit stubs: first configuration wins). */
    private ?array $frozenRow = null;

    private ?array $priorRow = null;

    protected function setUp(): void
    {
        $this->identification = $this->createMock(CodPaymentMethodResolverInterface::class);
        $this->ledger = $this->createMock(CodCollectionLedgerInterface::class);
        $this->ledger->method('findFrozenAmount')->willReturnCallback(
            fn (): ?array => $this->frozenRow
        );
        $this->ledger->method('findCollectedPrior')->willReturnCallback(
            fn (): ?array => $this->priorRow
        );
        $this->resolver = new SingleCollectionCodResolver($this->identification, $this->ledger);
        $this->frozenRow = null;
        $this->priorRow = null;
    }

    /**
     * @param string[] $codMethods
     */
    private function identifying(string ...$codMethods): void
    {
        $this->identification->method('isCod')->willReturnCallback(
            fn (string $method): bool => in_array($method, $codMethods, true)
        );
    }

    private function order(string $method, float $grandTotal, string $currency = 'VND', array $items = []): Order&MockObject
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn($method);

        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getGrandTotal')->willReturn($grandTotal);
        $order->method('getOrderCurrencyCode')->willReturn($currency);
        $order->method('getIncrementId')->willReturn('100000001');
        $order->method('getAllItems')->willReturn($items);

        return $order;
    }

    private function shipment(array $items = []): Shipment&MockObject
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getAllItems')->willReturn($items);

        return $shipment;
    }

    public function testCodOrderCollectsFullGrandTotalInOrderCurrency(): void
    {
        $this->identifying('cashondelivery');

        $decision = $this->resolver->resolve(
            $this->order('cashondelivery', 1250000.0),
            $this->shipment(),
            new CodCollectionAttempt('ghtk', 'ghtk-100000001-1')
        );

        $this->assertTrue($decision->isCollectible());
        $this->assertSame(1250000.0, $decision->getAmount());
        $this->assertSame('VND', $decision->getCurrencyCode());
    }

    public function testFrozenLedgerRowReplaysVerbatimWithoutReDeciding(): void
    {
        $this->identifying('cashondelivery');
        $this->frozenRow = ['amount' => '750000.0000', 'currency' => 'VND'];

        $decision = $this->resolver->resolve(
            $this->order('cashondelivery', 999999.0),
            $this->shipment(),
            new CodCollectionAttempt('ghtk', 'ghtk-100000001-1')
        );

        $this->assertTrue($decision->isCollectible());
        $this->assertSame(750000.0, $decision->getAmount(), 'frozen amount wins over a fresh grand_total');
        $this->assertSame('VND', $decision->getCurrencyCode());
    }

    public function testFrozenRowReplaysItsStoredCurrency(): void
    {
        // A hypothetical non-VND frozen row replays its own currency — the carrier's VND
        // gate then rejects it before any write/POST (the resolver never gates currency).
        $this->identifying('cashondelivery');
        $this->frozenRow = ['amount' => '1990.0000', 'currency' => 'USD'];

        $decision = $this->resolver->resolve(
            $this->order('cashondelivery', 999999.0, 'VND'),
            $this->shipment(),
            new CodCollectionAttempt('ghtk', 'ghtk-100000001-1')
        );

        $this->assertTrue($decision->isCollectible());
        $this->assertSame('USD', $decision->getCurrencyCode());
    }

    public function testCrossCarrierPriorIsRejected(): void
    {
        $this->identifying('cashondelivery');
        $this->priorRow = [
            'carrier_code' => 'ghn',
            'provider_reference' => 'GHNS41',
            'amount' => '500000.0000',
            'currency' => 'VND',
        ];

        $decision = $this->resolver->resolve(
            $this->order('cashondelivery', 1250000.0),
            $this->shipment(),
            new CodCollectionAttempt('ghtk', 'ghtk-100000001-2')
        );

        $this->assertTrue($decision->isRejected());
        $this->assertSame(CodCollectionDecision::REASON_COD_ALREADY_COLLECTED, $decision->getRejectionReason());
        $this->assertStringContainsString('ghn', (string) $decision->getRejectionMessage());
        $this->assertStringContainsString('GHNS41', (string) $decision->getRejectionMessage());
    }

    public function testNullAttemptWithPriorStillRejected(): void
    {
        $this->identifying('cashondelivery');
        $this->priorRow = [
            'carrier_code' => 'ghn',
            'provider_reference' => 'GHNS41',
            'amount' => '500000.0000',
            'currency' => 'VND',
        ];

        $decision = $this->resolver->resolve($this->order('cashondelivery', 1250000.0), $this->shipment(), null);

        $this->assertTrue($decision->isRejected());
        $this->assertSame(CodCollectionDecision::REASON_COD_ALREADY_COLLECTED, $decision->getRejectionReason());
    }

    public function testOwnReferenceFailedRowDoesNotBlockFreshDecision(): void
    {
        // A FAILED own row is excluded from both frozen replay and the prior rule (a
        // definitive provider rejection never collected anything) — fresh decision proceeds
        // (ledger mocks default to no rows in setUp).
        $this->identifying('cashondelivery');

        $decision = $this->resolver->resolve(
            $this->order('cashondelivery', 1250000.0),
            $this->shipment(),
            new CodCollectionAttempt('ghtk', 'ghtk-100000001-1')
        );

        $this->assertTrue($decision->isCollectible());
    }

    public function testNonCodOrderIsNotCodAndNeverBlocked(): void
    {
        $this->identifying('cashondelivery');

        $decision = $this->resolver->resolve(
            $this->order('mollie', 1250000.0),
            $this->shipment(),
            new CodCollectionAttempt('ghtk', 'ghtk-100000001-1')
        );

        $this->assertTrue($decision->isNotCod());
        $this->assertNull($decision->getAmount());
    }

    public function testEmptyPaymentMethodIsNotCod(): void
    {
        $this->identifying('cashondelivery');

        $decision = $this->resolver->resolve($this->order('', 1250000.0), $this->shipment(), null);

        $this->assertTrue($decision->isNotCod());
    }

    public function testUnconfiguredCodCandidateIsNotCod(): void
    {
        // P1 default: only `cashondelivery` — a custom method is simply not COD.
        $this->identifying();

        $decision = $this->resolver->resolve($this->order('custom_cod', 1250000.0), $this->shipment(), null);

        $this->assertTrue($decision->isNotCod());
    }

    public function testNonVndOrderCurrencyIsCarriedThroughForCarrierGating(): void
    {
        // DEC-TASKDFGFZ9-004: currency SUPPORT is a carrier concern — the decision carries
        // the ORDER currency (a USD order reaches the carrier as COLLECTIBLE-in-USD and the
        // carrier rejects it before any write/POST; the resolver never gates currency).
        $this->identifying('cashondelivery');

        $decision = $this->resolver->resolve($this->order('cashondelivery', 99.9, 'USD'), $this->shipment(), null);

        $this->assertTrue($decision->isCollectible());
        $this->assertSame(99.9, $decision->getAmount());
        $this->assertSame('USD', $decision->getCurrencyCode());
    }

    public function testZeroGrandTotalCodOrderStaysCodWithAmountZero(): void
    {
        // DEC-TASKDFGFZ9-004: a zero-total COD order keeps its COD classification — the
        // collection amount is visibly 0.0 (CODRisk still sees the payment method).
        $this->identifying('cashondelivery');

        $decision = $this->resolver->resolve($this->order('cashondelivery', 0.0, 'VND'), $this->shipment(), null);

        $this->assertTrue($decision->isCollectible());
        $this->assertSame(0.0, $decision->getAmount());
        $this->assertSame('VND', $decision->getCurrencyCode());
    }

    public function testNegativeGrandTotalIsRejectedAsInvalidAmount(): void
    {
        $this->identifying('cashondelivery');

        $decision = $this->resolver->resolve($this->order('cashondelivery', -5.0), $this->shipment(), null);

        $this->assertTrue($decision->isRejected());
        $this->assertSame(CodCollectionDecision::REASON_INVALID_ORDER_AMOUNT, $decision->getRejectionReason());
        $this->assertStringContainsString('-5', (string) $decision->getRejectionMessage());
    }

    public function testPartiallyShippedCodOrderIsRejected(): void
    {
        $this->identifying('cashondelivery');
        $ordered = $this->orderItem(101, 0.0, 2.0);
        $order = $this->order('cashondelivery', 1250000.0, 'VND', [$ordered]);
        $shipping = $this->shipment([$this->shipmentItem(101, $ordered, 1.0)]);

        $decision = $this->resolver->resolve($order, $shipping, null);

        $this->assertTrue($decision->isRejected());
        $this->assertSame(CodCollectionDecision::REASON_PARTIAL_SHIPMENT, $decision->getRejectionReason());
    }

    public function testFullyShippedCodOrderIsCollectible(): void
    {
        $this->identifying('cashondelivery');
        $ordered = $this->orderItem(101, 0.0, 2.0);
        $order = $this->order('cashondelivery', 1250000.0, 'VND', [$ordered]);
        $shipping = $this->shipment([$this->shipmentItem(101, $ordered, 2.0)]);

        $this->assertTrue($this->resolver->resolve($order, $shipping, null)->isCollectible());
    }

    public function testBundleContainerParentDoesNotCountAsUncovered(): void
    {
        $this->identifying('cashondelivery');
        $parent = $this->orderItem(201, 0.0, 1.0, 'bundle');
        $child = $this->orderItem(202, 0.0, 2.0);
        $order = $this->order('cashondelivery', 1250000.0, 'VND', [$parent, $child]);
        $shipping = $this->shipment([$this->shipmentItem(202, $child, 2.0)]);

        $this->assertTrue($this->resolver->resolve($order, $shipping, null)->isCollectible());
    }

    public function testDepositPaidCodOrderStillCollectsGrandTotal(): void
    {
        // P1 documents an accepted limitation: a partially prepaid COD order collects the
        // FULL grand total at the door — merchants must not use a COD method for
        // partially-paid orders (DEC-TASKDFGFZ9-002/003 consequences).
        $this->identifying('cashondelivery');

        $decision = $this->resolver->resolve($this->order('cashondelivery', 1250000.0), $this->shipment(), null);

        $this->assertTrue($decision->isCollectible());
        $this->assertSame(1250000.0, $decision->getAmount());
    }

    /**
     * @return OrderItem&MockObject
     */
    private function orderItem(int $itemId, float $qtyShipped, float $qtyOrdered, string $type = 'simple'): OrderItem&MockObject
    {
        $item = $this->createMock(OrderItem::class);
        $item->method('getIsVirtual')->willReturn(false);
        $item->method('getProductType')->willReturn($type);
        $item->method('getParentItem')->willReturn(null);
        $item->method('getQtyShipped')->willReturn($qtyShipped);
        $item->method('getQtyOrdered')->willReturn($qtyOrdered);
        $item->method('getItemId')->willReturn($itemId);

        return $item;
    }

    /**
     * @return ShipmentItem&MockObject
     */
    private function shipmentItem(int $orderItemId, OrderItem&MockObject $orderItem, float $qty): ShipmentItem&MockObject
    {
        $item = $this->createMock(ShipmentItem::class);
        $item->method('getOrderItemId')->willReturn($orderItemId);
        $item->method('getOrderItem')->willReturn($orderItem);
        $item->method('getQty')->willReturn($qty);

        return $item;
    }
}
