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

/**
 * Resolves the amount GHTK must collect at the door (pick_money) for a
 * shipment (SL-016 / DEC-SL016-001 §4) — kept OUT of the request mapper so
 * payment semantics evolve independently (deposit, gift card, partial
 * payment, split fulfillment).
 *
 * Default semantics: prepaid / non-COD → 0.0; COD → the still-collectible
 * order balance (base_total_due). A partial shipment of a COD order has no
 * safe allocation rule yet → implementors MUST fail fast instead of sending
 * a duplicated full-order COD amount.
 */
interface CodAmountResolverInterface
{
    /**
     * @param Order $order
     * @param Shipment $shipment The shipment being submitted (not yet persisted).
     * @return float Collectible amount in the order base currency (0.0 when nothing to collect).
     * @throws LocalizedException When a COD amount exists but cannot be allocated safely.
     */
    public function resolve(Order $order, Shipment $shipment): float;
}
