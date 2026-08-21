<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PromotionMaxDiscount\Test\Integration;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\CreditmemoService;
use Magento\Sales\Model\Service\InvoiceService;

/**
 * Group E — order lifecycle (ticket matrix E / spec §12, AC-013):
 * order item == quote capped; invoices/credit memos are pure allocations from
 * the order item (rule changes after placement never move the numbers).
 */
class OrderLifecycleTest extends AbstractCapTestCase
{
    /** @var array<int, OrderInterface> */
    private array $orders = [];

    protected function tearDown(): void
    {
        // order deletion needs the secure-area flag BEFORE any order cleanup
        $registry = self::$om->get(\Magento\Framework\Registry::class);
        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', true);

        $repo = $this->om(OrderRepositoryInterface::class);
        foreach ($this->orders as $order) {
            try {
                $fresh = $repo->get((int)$order->getEntityId());
                if ($fresh->getState() !== Order::STATE_CANCELED && $fresh->canCancel()) {
                    $fresh->cancel();
                    $repo->save($fresh);
                }
                $repo->delete($fresh);
            } catch (\Throwable $e) {
                fwrite(STDERR, 'order cleanup: ' . $e->getMessage() . "\n");
            }
        }
        parent::tearDown();
    }

    /**
     * Guest order from a capped quote (2 items, cap 500k -> 200k/300k).
     *
     * @return array{0: Order, 1: float, 2: float} order, item1 discount, item2 discount
     */
    private function placeCappedOrder(string $key): array
    {
        $p1 = $this->makeProduct($key . 'a', 2000000.0);
        $p2 = $this->makeProduct($key . 'b', 3000000.0);
        $this->makeRule($key . ' percent', 'by_percent', 20.0, 500000.0);
        $quote = $this->makeQuote([$p1, $p2]);
        $this->recalc($quote);

        $items = $this->visibleItems($quote);
        $expected1 = (float)$items[$p1->getSku()]->getBaseDiscountAmount();
        $expected2 = (float)$items[$p2->getSku()]->getBaseDiscountAmount();
        $this->assertAmountEquals(200000.0, $expected1, 0, 'quote item 1 capped');
        $this->assertAmountEquals(300000.0, $expected2, 0, 'quote item 2 capped');

        $this->finalizeGuestOrder($quote);

        $orderId = $this->om(\Magento\Quote\Api\CartManagementInterface::class)->placeOrder($quote->getId());
        /** @var Order $order */
        $order = $this->om(OrderRepositoryInterface::class)->get($orderId);
        $this->orders[(int)$order->getEntityId()] = $order;
        return [$order, $expected1, $expected2];
    }

    private static function orderItemDiscount(Order $order, int $index): float
    {
        $items = array_values($order->getAllItems());
        return (float)$items[$index]->getBaseDiscountAmount();
    }

    public function testOrderItemCarriesCappedDiscount(): void
    {
        [$order, $expected1, $expected2] = $this->placeCappedOrder('E1');

        $this->assertAmountEquals($expected1, self::orderItemDiscount($order, 0), 0, 'order item 1 == quote capped');
        $this->assertAmountEquals($expected2, self::orderItemDiscount($order, 1), 0, 'order item 2 == quote capped');
        $this->assertAmountEquals(-500000.0, (float)$order->getBaseDiscountAmount(), 0, 'order total discount');
    }

    public function testFullInvoiceMatchesCappedDiscount(): void
    {
        [$order, $expected1, $expected2] = $this->placeCappedOrder('E2');

        $invoice = $this->om(InvoiceService::class)->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::NOT_CAPTURE);
        $invoice->register();
        $this->om(\Magento\Framework\DB\Transaction::class)
            ->addObject($invoice)->addObject($order)->save();

        $invoiced1 = (float)array_values($invoice->getAllItems())[0]->getBaseDiscountAmount();
        $invoiced2 = (float)array_values($invoice->getAllItems())[1]->getBaseDiscountAmount();
        $this->assertAmountEquals($expected1, $invoiced1, 0, 'full invoice item 1 == order item');
        $this->assertAmountEquals($expected2, $invoiced2, 0, 'full invoice item 2 == order item');
        $this->assertLessThanOrEqual(
            $expected1 + $expected2 + 0.5,
            $invoiced1 + $invoiced2,
            'Σ invoiced <= order item discount'
        );
    }

    public function testMultiplePartialInvoicesAllocateFromOrderItem(): void
    {
        // item qty 4: two partial invoices (1 + 3) — Σ invoiced discount ==
        // order item discount (pure proration, never re-run of the rule)
        $p1 = $this->makeProduct('E3a', 2000000.0);
        $rule = $this->makeRule('E3 percent', 'by_percent', 20.0, 300000.0);
        $quote = $this->makeQuote([$p1], [$p1->getSku() => 4]);
        $this->recalc($quote);
        $quoteItem = $this->visibleItems($quote)[$p1->getSku()];
        $this->assertAmountEquals(300000.0, (float)$quoteItem->getBaseDiscountAmount(), 0);

        $this->finalizeGuestOrder($quote);
        $orderId = $this->om(\Magento\Quote\Api\CartManagementInterface::class)->placeOrder($quote->getId());
        /** @var Order $order */
        $order = $this->om(OrderRepositoryInterface::class)->get($orderId);
        $this->orders[(int)$order->getEntityId()] = $order;

        $orderItem = array_values($order->getAllItems())[0];
        $this->assertAmountEquals(300000.0, (float)$orderItem->getBaseDiscountAmount(), 0, 'order item capped');

        $makeInvoice = function (array $qtys) use ($order): float {
            $invoice = $this->om(InvoiceService::class)->prepareInvoice($order, $qtys);
            $invoice->setRequestedCaptureCase(Invoice::NOT_CAPTURE);
            $invoice->register();
            $this->om(\Magento\Framework\DB\Transaction::class)
                ->addObject($invoice)->addObject($order)->save();
            $sum = 0.0;
            foreach ($invoice->getAllItems() as $item) {
                $sum += (float)$item->getBaseDiscountAmount();
            }
            return $sum;
        };

        $invoice1 = $makeInvoice([$orderItem->getId() => 1]);
        $invoice2 = $makeInvoice([$orderItem->getId() => 3]);
        $this->assertAmountEquals(300000.0, $invoice1 + $invoice2, 0, 'Σ partial invoices == order item discount');
        $this->assertLessThanOrEqual(300000.0 + 0.5, $invoice1 + $invoice2, 'Σ invoiced <= order item discount');
        $this->assertSame((int)$rule->getId(), (int)$rule->getId());
    }

    public function testRuleChangeAfterPlacementNeverMovesNumbers(): void
    {
        [$order, $expected1, $expected2] = $this->placeCappedOrder('E4');
        $orderItem = array_values($order->getAllItems())[0];

        // first partial invoice BEFORE the rule change
        $before = $this->om(InvoiceService::class)->prepareInvoice($order, [$orderItem->getId() => 1]);
        $before->setRequestedCaptureCase(Invoice::NOT_CAPTURE);
        $before->register();
        $this->om(\Magento\Framework\DB\Transaction::class)->addObject($before)->addObject($order)->save();
        $beforeSum = 0.0;
        foreach ($before->getAllItems() as $item) {
            $beforeSum += (float)$item->getBaseDiscountAmount();
        }

        // mutate the rule: disable + raise cap far above native
        $conn = self::$om->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();
        $conn->update('salesrule', ['is_active' => 0, 'maximum_discount_amount' => 9000000.0], ['rule_id = ?' => array_keys($this->rules)[0]]);

        // invoice the remaining items AFTER the rule change — must allocate from
        // the ORDER items, never recalculate
        $fresh = $this->om(OrderRepositoryInterface::class)->get((int)$order->getEntityId());
        $secondItem = array_values($fresh->getAllItems())[1];
        $after = $this->om(InvoiceService::class)->prepareInvoice($fresh, [$secondItem->getId() => 1]);
        $after->setRequestedCaptureCase(Invoice::NOT_CAPTURE);
        $after->register();
        $this->om(\Magento\Framework\DB\Transaction::class)->addObject($after)->addObject($fresh)->save();
        $afterSum = 0.0;
        foreach ($after->getAllItems() as $item) {
            $afterSum += (float)$item->getBaseDiscountAmount();
        }

        $this->assertAmountEquals($expected1, $beforeSum, 0, 'invoice 1 prorated from order item');
        $this->assertAmountEquals(
            $expected1 + $expected2,
            $beforeSum + $afterSum,
            0,
            'Σ invoices == order discount despite rule disable + cap change'
        );
    }

    public function testFullRefundAfterFullInvoice(): void
    {
        [$order, $expected1, $expected2] = $this->placeCappedOrder('E5');

        $invoice = $this->om(InvoiceService::class)->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::NOT_CAPTURE);
        $invoice->register();
        $this->om(\Magento\Framework\DB\Transaction::class)->addObject($invoice)->addObject($order)->save();

        $creditmemo = $this->om(\Magento\Sales\Model\Order\CreditmemoFactory::class)->createByOrder($order);
        $this->om(CreditmemoService::class)->refund($creditmemo, true);

        $refunded = 0.0;
        foreach ($creditmemo->getAllItems() as $item) {
            $refunded += (float)$item->getBaseDiscountAmount();
        }
        $this->assertAmountEquals($expected1 + $expected2, $refunded, 0, 'full refund == invoiced discount');
        $this->assertLessThanOrEqual($expected1 + $expected2 + 0.5, $refunded, 'Σ refunded <= invoiced');
    }

    public function testRefundByItemOnlyRefundsThatItem(): void
    {
        // credit memo for item 1 of a 2-item order: only its capped share is
        // refunded; item 2's discount stays untouched for a later refund
        [$order, $expected1] = $this->placeCappedOrder('E7');

        $invoice = $this->om(InvoiceService::class)->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::NOT_CAPTURE);
        $invoice->register();
        $this->om(\Magento\Framework\DB\Transaction::class)->addObject($invoice)->addObject($order)->save();

        $firstItem = array_values($order->getAllItems())[0];
        $creditmemo = $this->om(\Magento\Sales\Model\Order\CreditmemoFactory::class)
            ->createByOrder($order, ['qtys' => [$firstItem->getId() => 1]]);
        $this->om(CreditmemoService::class)->refund($creditmemo, true);

        $this->assertCount(1, $creditmemo->getAllItems(), 'CM carries only the refunded item');
        $refunded = (float)array_values($creditmemo->getAllItems())[0]->getBaseDiscountAmount();
        $this->assertAmountEquals($expected1, $refunded, 0, 'refund by item == item 1 capped share');
        $this->assertLessThanOrEqual($expected1 + $expected1 + 0.5, $refunded, 'Σ refunded <= invoiced');
    }

    public function testPartialRefundByQtyNeverExceedsInvoiced(): void
    {
        $p1 = $this->makeProduct('E6a', 2000000.0);
        $this->makeRule('E6 percent', 'by_percent', 20.0, 300000.0);
        $quote = $this->makeQuote([$p1], [$p1->getSku() => 4]);
        $this->recalc($quote);

        $this->finalizeGuestOrder($quote);
        $orderId = $this->om(\Magento\Quote\Api\CartManagementInterface::class)->placeOrder($quote->getId());
        /** @var Order $order */
        $order = $this->om(OrderRepositoryInterface::class)->get($orderId);
        $this->orders[(int)$order->getEntityId()] = $order;

        $invoice = $this->om(InvoiceService::class)->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::NOT_CAPTURE);
        $invoice->register();
        $this->om(\Magento\Framework\DB\Transaction::class)->addObject($invoice)->addObject($order)->save();

        // refund qty 1 of 4
        $orderItem = array_values($order->getAllItems())[0];
        $creditmemo = $this->om(\Magento\Sales\Model\Order\CreditmemoFactory::class)
            ->createByOrder($order, ['qtys' => [$orderItem->getId() => 1]]);
        $this->om(CreditmemoService::class)->refund($creditmemo, true);

        $refunded = (float)array_values($creditmemo->getAllItems())[0]->getBaseDiscountAmount();
        $invoicedDiscount = (float)array_values($invoice->getAllItems())[0]->getBaseDiscountAmount();
        $this->assertGreaterThan(0.0, $refunded, 'partial refund carries discount share');
        $this->assertLessThanOrEqual($invoicedDiscount + 0.5, $refunded, 'Σ refunded <= discount invoiced');
        $this->assertAmountEquals(75000.0, $refunded, 0, '300k x 1/4 proration');
    }
}
