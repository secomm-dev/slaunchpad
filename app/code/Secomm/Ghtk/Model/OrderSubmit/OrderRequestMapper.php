<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\OrderSubmit;

use Magento\Shipping\Model\Shipment\Request;
use Secomm\Ghtk\Model\Address\GhtkAddress;
use Secomm\Ghtk\Model\Address\PickupAddress;

/**
 * Assembles the GHTK Create Order payload (SL-016 / DEC-SL016-001).
 *
 * TASK-KCXKVR — corrected against the OFFICIAL Create Order contract
 * (api.ghtk.vn submit-order-express; evidence SPIKE-A1DGPY §2):
 *
 * - Top-level request is `{"order": {...}, "products": [...]}` — the previous
 *   flattened shape (`partner_order_id`, `order` carrying the products array)
 *   does not exist in the official contract;
 * - the deterministic Secomm partner key rides in `order.id` (the duplicate-
 *   detection key — recovered via ORDER_ID_EXIST after TASK-BE5YD2);
 * - `products[].weight` is in KILOGRAMS per the official docs ("The unit of
 *   weight GHTK uses for each product is kilograms (KG)"); the project-internal
 *   gram values are converted HERE — the GHTK API boundary. `weight_option` is
 *   omitted (documented default = kilogram) so no mixed-unit payload exists;
 * - `order.total_weight` (Double, kg) carries the resolved shipment weight
 *   (admin-entered packages or catalog-derived) — GHTK would otherwise compute
 *   it from products.weight;
 * - `is_freeship = 1`: Magento charged shipping at checkout → the recipient
 *   pays ONLY pick_money at the door (no double charge);
 * - `pick_money` = the RESOLVED COD amount only (CodAmountResolverInterface) —
 *   never grand_total. `pick_option` deliberately omitted: official default
 *   `cod` is correct (it is a LOGISTICS pickup mode, not a payment selector);
 * - `hamlet = "Khác"` per the official docs ("use Khác when not applicable")
 *   since detailed hamlet/street parsing is not built.
 *
 * Contract notes (staging-gated, NEEDS_RUNTIME_VERIFICATION — Q-EXT):
 * `district` optionality on 2-level post-2025 addresses is documented REQUIRED
 * but unverified at runtime — the address adapter output is serialized as-is.
 *
 * @return array<string, mixed> `{"order": {...}, "products": [...]}` payload
 */
class OrderRequestMapper
{
    /** Official docs: minimum representable product weight — 1 gram in kg. */
    private const MIN_PRODUCT_WEIGHT_KG = 0.001;

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
        $order = [
            'id' => $partnerOrderId,
            'pick_name' => (string) ($request->getShipperContactPersonName()
                ?: $request->getShipperContactCompanyName()),
            'pick_tel' => (string) $request->getShipperContactPhoneNumber(),
            'pick_address' => (string) $request->getShipperAddressStreet(),
            'is_freeship' => 1,
            'pick_money' => (int) round($codAmount),
            'value' => (int) round($this->declaredValue($products)),
            'transport' => $transport,
            // Destination (VN 2-level, canonical GHTK text from the address adapter).
            'name' => trim((string) $request->getRecipientContactPersonName()),
            'tel' => (string) $request->getRecipientContactPhoneNumber(),
            'address' => trim((string) $request->getRecipientAddressStreet()),
            'province' => $dest->province,
            'ward' => $dest->ward,
            'hamlet' => 'Khác',
            'total_weight' => $this->gramsToKilograms((float) $weightGram),
        ];
        if ($dest->district !== null) {
            $order['district'] = $dest->district;
        }

        if ($pickup->hasPickAddressId()) {
            $order['pick_address_id'] = $pickup->pickAddressId; // official priority field
        } else {
            $order['pick_province'] = (string) $pickup->province;
            $order['pick_ward'] = (string) $pickup->ward;
            if ($pickup->district !== null) {
                $order['pick_district'] = $pickup->district;
            }
        }

        return ['order' => $order, 'products' => $this->productsPayload($products)];
    }

    /**
     * Project-internal product rows carry GRAM weights (ShipmentWeightCalculator /
     * buildProducts); the GHTK boundary requires KILOGRAMS — converted here.
     *
     * @param array<int, array{name: string, weight: int, quantity: int, price: float}> $products
     * @return array<int, array{name: string, weight: float, quantity: int, price: float}>
     */
    private function productsPayload(array $products): array
    {
        $payload = [];
        foreach ($products as $product) {
            $payload[] = [
                'name' => (string) ($product['name'] ?? 'Item'),
                'weight' => $this->gramsToKilograms((float) ($product['weight'] ?? 0)),
                'quantity' => max(1, (int) ($product['quantity'] ?? 1)),
                'price' => (float) ($product['price'] ?? 0),
            ];
        }

        return $payload;
    }

    private function gramsToKilograms(float $grams): float
    {
        return max(self::MIN_PRODUCT_WEIGHT_KG, round($grams / 1000, 6));
    }

    /**
     * @param array<int, array{name: string, weight: int, quantity: int, price: float}> $products
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
