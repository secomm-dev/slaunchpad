<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Sales\Model\Order\Shipment;
use Secomm\ShippingCore\Api\ShippingContextInterface;

/**
 * Builds ShippingContext per flow (SL-015; shipment path added by SL-016).
 * Purpose-built factory — not the ObjectManager-generated one; an immutable
 * scalar DTO has no generic create().
 */
class ShippingContextFactory
{
    public function fromRateRequest(RateRequest $request, string $carrierCode): ShippingContextInterface
    {
        return $this->create(
            $request->getStoreId() !== null ? (int) $request->getStoreId() : null,
            $carrierCode,
            $request->getQuoteId() !== null ? (int) $request->getQuoteId() : null
        );
    }

    /**
     * Shipment path (SL-016): the label-submit flow builds the SAME context
     * shape as the rate path, so both flows resolve the origin through the
     * same OriginProviderInterface (DEC-SL015-001 §2).
     */
    public function fromShipment(Shipment $shipment, string $carrierCode): ShippingContextInterface
    {
        $order = $shipment->getOrder();

        return $this->create(
            $shipment->getStoreId() !== null ? (int) $shipment->getStoreId() : null,
            $carrierCode,
            $order !== null && $order->getQuoteId() !== null ? (int) $order->getQuoteId() : null
        );
    }

    public function create(
        ?int $storeId = null,
        ?string $carrierCode = null,
        ?int $quoteId = null,
        ?string $sourceCode = null
    ): ShippingContextInterface {
        return new ShippingContext($storeId, null, $carrierCode, $quoteId, $sourceCode);
    }
}
