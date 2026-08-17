<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\OrderSubmit;

use Magento\Shipping\Model\Shipment\Request;
use Secomm\Ghtk\Model\Address\GhtkAddress;
use Secomm\Ghtk\Model\Address\PickupAddress;

/**
 * Assembles the GHTK Submit Order payload (SL-016 / DEC-SL016-001).
 *
 * Contract notes (Q-EXT still open — this mapper isolates any drift):
 * - pickup from the resolved PickupAddress (metadata pick_address_id wins,
 *   DEC-021); shipper contact (pick_name/pick_tel) from the NATIVE
 *   Shipment\Request (admin user + store_information, validated by core);
 * - pick_money = the RESOLVED COD amount only (CodAmountResolverInterface) —
 *   never grand_total;
 * - is_freeship = 1: Magento charged shipping at checkout; GHTK must not
 *   collect it again at the door (no double charge);
 * - value = declared value of the shipped items (subtotal);
 * - weight in grams (DEC-022 Q2 — same unit as the fee API).
 *
 * @return array<string, mixed>
 */
class OrderRequestMapper
{
    public function map(
        Request $request,
        PickupAddress $pickup,
        GhtkAddress $dest,
        int $weightGram,
        float $codAmount,
        string $partnerOrderId,
        array $products,
        string $transport
    ): array {
        $payload = [
            'pick_name' => (string) ($request->getShipperContactPersonName()
                ?: $request->getShipperContactCompanyName()),
            'pick_tel' => (string) $request->getShipperContactPhoneNumber(),
            'pick_address' => (string) $request->getShipperAddressStreet(),
            'is_freeship' => 1,
            'pick_money' => (int) round($codAmount),
            'value' => (int) round($this->declaredValue($products)),
            'weight_option' => 'gram',
            'partner_order_id' => $partnerOrderId,
            'transport' => $transport,
            // Destination (VN 2-level, normalized GHTK names).
            'name' => trim((string) $request->getRecipientContactPersonName()),
            'tel' => (string) $request->getRecipientContactPhoneNumber(),
            'address' => trim((string) $request->getRecipientAddressStreet()),
            'province' => $dest->province,
            'ward' => $dest->ward,
            'hamlet' => 'Khác',
            'weight' => $weightGram,
            'order' => $products,
        ];
        if ($dest->district !== null) {
            $payload['district'] = $dest->district;
        }

        if ($pickup->hasPickAddressId()) {
            $payload['pick_address_id'] = $pickup->pickAddressId;
        } else {
            $payload['pick_province'] = (string) $pickup->province;
            $payload['pick_ward'] = (string) $pickup->ward;
            if ($pickup->district !== null) {
                $payload['pick_district'] = $pickup->district;
            }
        }

        return $payload;
    }

    /**
     * @param array<int, array{name: string, quantity: int, weight: int, price: float}> $products
     */
    private function declaredValue(array $products): float
    {
        $total = 0.0;
        foreach ($products as $product) {
            $total += (float) ($product['price'] ?? 0) * (int) ($product['quantity'] ?? 0);
        }

        return $total;
    }
}
