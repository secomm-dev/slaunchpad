<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Cod\Model;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Secomm\Cod\Api\CodCollectionAttemptInterface;
use Secomm\Cod\Api\CodCollectionDecisionInterface;
use Secomm\Cod\Api\CodCollectionLedgerInterface;
use Secomm\Cod\Api\CodCollectionResolverInterface;
use Secomm\Cod\Api\CodPaymentMethodResolverInterface;

/**
 * TASK-DFGFZ9 phase 2/3 (DEC-TASKDFGFZ9-002/003/004) — Launchpad P1 COD collection policy;
 * the ONLY place the single-collection decision logic lives (swappable via DI preference —
 * the contract stays policy-agnostic).
 *
 * P1 rules, in evaluation order:
 *  0. THIS attempt already froze a ledger amount (status != FAILED) → replay it verbatim —
 *     a retry never re-decides the collection (persisted-wins, across all carriers);
 *  1. the collection ledger shows a prior collection of this order through a DIFFERENT
 *     (carrier, reference) → REJECTED — the ledger is consulted by the resolver itself, so a
 *     caller cannot bypass the rule (cross-carrier safe by construction);
 *  2. the payment method is not COD → NOT_COD (non-COD shipments are never blocked by any
 *     COD rule);
 *  3. grand_total < 0 → REJECTED — a negative collection amount is an unusable data defect;
 *  4. the shipment does not cover the full shippable quantity → REJECTED — P1 collects the
 *     full order grand total ONCE, so a COD order must ship complete in one shipment;
 *  5. otherwise COLLECTIBLE: `round(grand_total, 4)` in the ORDER currency — including
 *     **0.0**: a COD-method order with a zero total STAYS COD (consumers such as CODRisk
 *     still see the payment method); the carrier maps the zero amount (or rejects it on its
 *     own currency/collection rules). Currency SUPPORT is a CARRIER concern — carriers gate
 *     their supported currency (GHTK/GHN: VND) before recordPending/POST, never converting;
 *     this resolver does not gate currency.
 *
 * The per-order claim itself is engine-enforced in the ledger (`active_order_claim` UNIQUE —
 * DEC-TASKDFGFZ9-004): recordPending is INSERT-first and a losing concurrent attempt
 * receives CodClaimConflictException. Deliberately NOT supported at P1: deposits/partial
 * payments (grand_total is collected even when part was prepaid), multi-shipment allocation,
 * currency conversion. Ledger rows never reconcile money — provider acceptance of a COD
 * order does not mean Magento received it.
 */
final class SingleCollectionCodResolver implements CodCollectionResolverInterface
{
    public function __construct(
        private readonly CodPaymentMethodResolverInterface $codPaymentMethodResolver,
        private readonly CodCollectionLedgerInterface $collectionLedger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(
        Order $order,
        Shipment $shipment,
        ?CodCollectionAttemptInterface $attempt
    ): CodCollectionDecisionInterface {
        // (0) Persisted-wins: a retry of THIS attempt replays its frozen amount verbatim.
        if ($attempt !== null && ($frozen = $this->collectionLedger->findFrozenAmount($attempt)) !== null) {
            return CodCollectionDecision::collectible((float) $frozen['amount'], (string) $frozen['currency']);
        }

        // (1) One collection per order — the ledger is the single cross-carrier source of
        // truth; a null attempt still runs this check (excluding nothing).
        $prior = $this->collectionLedger->findCollectedPrior((int) $order->getEntityId(), $attempt);
        if ($prior !== null) {
            return CodCollectionDecision::rejected(
                CodCollectionDecisionInterface::REASON_COD_ALREADY_COLLECTED,
                sprintf(
                    'Order #%s already has a COD collection of %s %s via %s shipment "%s" — '
                    . 'P1 collects COD once per order.',
                    $order->getIncrementId(),
                    $prior['amount'],
                    $prior['currency'],
                    $prior['carrier_code'],
                    $prior['provider_reference']
                )
            );
        }

        $method = $order->getPayment() !== null ? (string) $order->getPayment()->getMethod() : '';
        if ($method === '' || !$this->codPaymentMethodResolver->isCod($method)) {
            return CodCollectionDecision::notCod();
        }

        $currency = (string) $order->getOrderCurrencyCode();
        $grandTotal = (float) $order->getGrandTotal();
        if ($grandTotal < 0.0) {
            return CodCollectionDecision::rejected(
                CodCollectionDecisionInterface::REASON_INVALID_ORDER_AMOUNT,
                sprintf(
                    'Order #%s has a negative grand total (%s %s) — the COD collection amount is unusable.',
                    $order->getIncrementId(),
                    $grandTotal,
                    $currency !== '' ? $currency : '(unset)'
                )
            );
        }

        // Zero grand_total STAYS COD (collectible 0.0 — nothing to collect at the door, but
        // consumers still see the payment method); only a negative total is a defect.
        $amount = round($grandTotal, 4);

        if ($this->isPartialShipment($order, $shipment)) {
            return CodCollectionDecision::rejected(
                CodCollectionDecisionInterface::REASON_PARTIAL_SHIPMENT,
                sprintf(
                    'Order #%s still has unshipped items — P1 collects the full order total (%s %s) '
                    . 'exactly once, so a COD order must ship complete in one shipment.',
                    $order->getIncrementId(),
                    $amount,
                    $currency
                )
            );
        }

        return CodCollectionDecision::collectible($amount, $currency);
    }

    /**
     * A shipment is partial when any shippable order item is not fully covered
     * by already-saved shipments plus the shipment being created now.
     */
    private function isPartialShipment(Order $order, Shipment $shipment): bool
    {
        $shippingNow = [];
        foreach ($shipment->getAllItems() as $item) {
            $orderItemId = (int) $item->getOrderItemId();
            if ($item->getOrderItem() !== null && $item->getOrderItem()->getIsVirtual()) {
                continue;
            }
            $shippingNow[$orderItemId] = ($shippingNow[$orderItemId] ?? 0) + (float) $item->getQty();
        }

        foreach ($order->getAllItems() as $orderItem) {
            if ($orderItem->getIsVirtual()) {
                continue;
            }
            $type = (string) $orderItem->getProductType();
            if ($orderItem->getParentItem() === null && ($type === 'configurable' || $type === 'bundle')) {
                continue; // container parent — children carry qty
            }

            $covered = (float) $orderItem->getQtyShipped() + ($shippingNow[(int) $orderItem->getItemId()] ?? 0);
            if ($covered + 0.0001 < (float) $orderItem->getQtyOrdered()) {
                return true;
            }
        }

        return false;
    }
}
