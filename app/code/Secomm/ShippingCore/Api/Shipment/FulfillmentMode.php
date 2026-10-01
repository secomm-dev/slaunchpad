<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Shipment;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — generic shipment fulfillment mode, owned by
 * Secomm_ShippingCore.
 *
 * - ONLINE: Magento shipment + provider shipment via the carrier integration (the normal path).
 * - OFFLINE: Magento shipment only — an explicit admin escape when carrier create is blocked by
 *   a deterministic constraint; no provider API call, no provider anchor, no retry/reconciliation
 *   entry, no carrier COD claim. Fulfillment/tracking is manual.
 *
 * This is a FULFILLMENT concept. It deliberately shares nothing with the checkout-rate vocabulary
 * (`Api/Rate/RateSourceMode`, `Api/Fallback/*`) — those describe quote-time rate sourcing and
 * must never drive a fulfillment decision.
 */
final class FulfillmentMode
{
    public const ONLINE = 'ONLINE';
    public const OFFLINE = 'OFFLINE';

    /**
     * Absence of metadata means ONLINE — only explicit OFFLINE records are ever written.
     */
    public static function isOffline(?string $mode): bool
    {
        return $mode === self::OFFLINE;
    }
}
