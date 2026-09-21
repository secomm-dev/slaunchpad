<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Launchpad\MageplazaExtraFeeFix\Service;

use Magento\Sales\Model\Order;

/**
 * Shared helper for Mageplaza Extra Fee order-state logic.
 *
 * Centralises two predicates previously duplicated across
 * StateResolverPlugin and CanCreditmemoPlugin.
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
        $mpExtraFee = $order->getData('mp_extra_fee');
        if (empty($mpExtraFee)) {
            return false;
        }

        // Column may already be decoded to array in some contexts.
        $decoded = is_string($mpExtraFee) ? json_decode($mpExtraFee, true) : $mpExtraFee;

        if (!is_array($decoded) || empty($decoded['totals']) || !is_array($decoded['totals'])) {
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
