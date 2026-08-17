<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\OrderSubmit;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Secomm\Ghtk\Model\Config\GhtkConfig;

/**
 * Default COD resolution (SL-016 / DEC-SL016-001 §4-5):
 *
 * - payment method not in the configured COD list (default: cashondelivery)
 *   → 0.0 — prepaid orders never collect at the door;
 * - COD → base_total_due (grand total minus already paid/captured): deposit,
 *   gift-card and partial online payments are handled naturally — the carrier
 *   collects only what is still outstanding, never grand_total blindly;
 * - partial shipment + outstanding COD > 0 → LocalizedException (fail fast —
 *   no allocation engine in this release; DEC-SL016-001 §5).
 */
class DefaultCodAmountResolver implements CodAmountResolverInterface
{
    public function __construct(
        private GhtkConfig $config
    ) {
    }

    public function resolve(Order $order, Shipment $shipment): float
    {
        $method = $order->getPayment() !== null ? (string) $order->getPayment()->getMethod() : '';
        if ($method === '' || !in_array($method, $this->config->getCodMethodCodes((int) $order->getStoreId()), true)) {
            return 0.0; // prepaid / non-COD
        }

        $due = round(max(0.0, (float) $order->getBaseTotalDue()), 4);
        if ($due <= 0.0) {
            return 0.0; // fully paid COD-style order (e.g. deposit covered the rest)
        }

        if ($this->isPartialShipment($order, $shipment)) {
            throw new LocalizedException(
                __(
                    'This order still has unshipped items and an outstanding COD amount of %1. '
                    . 'Partial COD shipments are not supported yet — ship the full order in one '
                    . 'shipment, or resolve the COD amount explicitly.',
                    number_format($due, 0)
                )
            );
        }

        return $due;
    }

    /**
     * A shipment is partial when any shippable order item is not fully covered
     * by already-saved shipments plus the shipment being submitted now.
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
