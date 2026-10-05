<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Launchpad\MageplazaExtraFeeFix\Service;

use Magento\Framework\Model\AbstractModel;
use Magento\Sales\Model\Order;

/**
 * Shared helper for Mageplaza Extra Fee order-state logic.
 *
 * Centralises predicates previously duplicated across
 * StateResolverPlugin and CanCreditmemoPlugin, plus the
 * mp_extra_fee JSON parser shared with the EInvoice integration.
 */
class ExtraFeeOrderHelper
{
    /**
     * Return true when the order carries at least one non-refundable
     * Mageplaza Extra Fee (rf = 0 in the mp_extra_fee JSON column).
     *
     * JSON structure stored by Mageplaza on sales_order.mp_extra_fee:
     * {
     *   "totals": [
     *     { "code": "mp_extra_fee_rule_1_auto", "rf": 0, "value": 10000, ... },
     *     { "code": "mp_extra_fee_rule_2_auto", "rf": 1, "value": 5000,  ... }
     *   ]
     * }
     * rf = 0  Non-Refundable / rf = 1  Refundable
     */
    public function hasNonRefundableExtraFee(Order $order): bool
    {
        $decoded = $this->decodeExtraFeeJson($order);

        if (empty($decoded['totals']) || !is_array($decoded['totals'])) {
            return false;
        }

        foreach ($decoded['totals'] as $fee) {
            if (isset($fee['rf']) && (int)$fee['rf'] === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse the Mageplaza extra-fee totals stored on an order or credit memo
     * (sales_order.mp_extra_fee / sales_creditmemo.mp_extra_fee JSON column).
     *
     * Non-refundable fees (rf = 0, e.g. "Phí bảo hiểm hàng hóa") never reach
     * the credit memo JSON — Mageplaza blocks their refund — so both entity
     * types can be parsed the same way.
     *
     * @param AbstractModel $salesEntity Order or Creditmemo carrying mp_extra_fee.
     * @return array<int, array{
     *     code: string,
     *     title: string,
     *     amount_excl_tax: float,
     *     tax_amount: float,
     *     refundable: bool
     * }>
     */
    public function getFeeTotals(AbstractModel $salesEntity): array
    {
        $decoded = $this->decodeExtraFeeJson($salesEntity);
        $fees = [];

        foreach ($decoded['totals'] ?? [] as $fee) {
            if (!is_array($fee)) {
                continue;
            }

            $amountExclTax = (float) ($fee['value_excl_tax'] ?? $fee['value'] ?? 0);
            $amountInclTax = (float) ($fee['value_incl_tax'] ?? $fee['value'] ?? $amountExclTax);
            $taxAmount = max(0.0, round($amountInclTax - $amountExclTax, 2));

            if ($amountExclTax + $taxAmount <= 0.0) {
                continue;
            }

            $fees[] = [
                'code' => (string) ($fee['code'] ?? ''),
                'title' => (string) ($fee['title'] ?? $fee['label'] ?? ''),
                'amount_excl_tax' => $amountExclTax,
                'tax_amount' => $taxAmount,
                'refundable' => (int) ($fee['rf'] ?? 0) === 1,
            ];
        }

        return $fees;
    }

    /**
     * Decode the raw mp_extra_fee column value (JSON string or already-decoded array).
     *
     * @param AbstractModel $salesEntity
     * @return array<string, mixed>
     */
    private function decodeExtraFeeJson(AbstractModel $salesEntity): array
    {
        $mpExtraFee = $salesEntity->getData('mp_extra_fee');
        if (empty($mpExtraFee)) {
            return [];
        }

        // Column may already be decoded to array in some contexts.
        $decoded = is_string($mpExtraFee) ? json_decode($mpExtraFee, true) : $mpExtraFee;

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Return true when every real (non-dummy) order item has been
     * fully refunded or canceled.
     *
     * Dummy items (configurable/bundle parent rows) are skipped because
     * they carry no own qty — only their children do.
     *
     * Epsilon of 0.0001 guards against float rounding artefacts.
     */
    public function areAllItemsRefundedOrCanceled(Order $order): bool
    {
        $items = $order->getAllItems();
        if (empty($items)) {
            return false;
        }

        foreach ($items as $item) {
            if ($item->isDummy()) {
                continue;
            }

            $qtyOrdered  = (float)$item->getQtyOrdered();
            $qtyRefunded = (float)$item->getQtyRefunded();
            $qtyCanceled = (float)$item->getQtyCanceled();

            if (($qtyRefunded + $qtyCanceled) < ($qtyOrdered - 0.0001)) {
                return false;
            }
        }

        return true;
    }
}
