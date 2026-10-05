<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Shipment;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Sales\Api\Data\ShipmentInterface;
use Secomm\ShippingCore\Api\Shipment\FulfillmentMode;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — the ONE read seam for the generic fulfillment mode.
 *
 * Two independent signals, both explicit:
 * - REQUEST intent: the in-flight admin save posted `shipment[fulfillment_mode]=OFFLINE`
 *   (strict equality — anything absent/garbage is ONLINE);
 * - PERSISTED metadata: the `secomm_fulfillment` marker on `sales_shipment.packages`.
 *
 * Carriers consult this resolver to gate provider submission (OFFLINE shipments must never
 * reach the provider — on the fresh offline save via the request intent, and on every later
 * re-save via the persisted metadata, since `sales_order_shipment_save_commit_after` fires on
 * EVERY save and an offline shipment has no provider anchor to idempotency-guard). Checkout-rate
 * vocabulary (RateSourceMode / FallbackEligibility) is deliberately not consulted — those are
 * quote-time concepts.
 */
class FulfillmentModeResolver
{
    /** Key inside the posted `shipment[]` array carrying the offline intent. */
    public const REQUEST_PARAM = 'fulfillment_mode';

    public function __construct(
        private readonly HttpRequest $request,
        private readonly FulfillmentMetadataPersister $metadataPersister,
        private readonly OfflineCapabilityPool $capabilityPool
    ) {
    }

    /**
     * True when the CURRENT request explicitly posts the offline fulfillment intent.
     */
    public function isOfflineIntent(): bool
    {
        $posted = $this->request->getParam('shipment');
        $mode = is_array($posted) ? (string) ($posted[self::REQUEST_PARAM] ?? '') : '';

        return FulfillmentMode::isOffline($mode);
    }

    /**
     * Fulfillment mode of a shipment: persisted OFFLINE record, else ONLINE (default).
     */
    public function forShipment(ShipmentInterface $shipment): string
    {
        return $this->metadataPersister->read($shipment) !== null
            ? FulfillmentMode::OFFLINE
            : FulfillmentMode::ONLINE;
    }

    /**
     * Carrier that an offline shipment on this raw shipping method would travel under —
     * null when the method matches no ENABLED offline capability.
     */
    public function resolveCarrierCode(?string $rawShippingMethod): ?string
    {
        if ($rawShippingMethod === null || $rawShippingMethod === '') {
            return null;
        }

        return $this->capabilityPool->findForShippingMethod($rawShippingMethod)?->getCarrierCode();
    }
}
