<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Address;

use Secomm\Ghtk\Model\Origin\GhtkOriginProvider;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;
use Secomm\ShippingCore\Api\OriginInterface;

/**
 * Maps a normalized runtime origin onto the GHTK pickup payload and enforces
 * the strict pickup gate (DEC-021, SL-015):
 *
 * 1. carrier metadata ghtk.pick_address_id present → sent alone, no pickup
 *    mapping needed (preferred);
 * 2. origin carries regionId + ward → names are normalized to GHTK-recognised
 *    names through the SAME canonical pipeline as the destination
 *    (GhtkAddressAdapter — canonical name_vi + optional override, TEXT_NATIVE);
 *    unresolvable → null → carrier hides;
 * 3. origin carries names only (legacy merchant-entered config) → used as-is;
 * 4. nothing usable → null → carrier hides (never an ambiguous request).
 */
class PickupAddressResolver
{
    public function __construct(
        private GhtkAddressAdapter $destinationResolver
    ) {
    }

    public function resolve(OriginInterface $origin, string $operation): ?PickupAddress
    {
        $pickAddressId = $origin->getMetadata(GhtkOriginProvider::METADATA_PICK_ADDRESS_ID);
        if (is_string($pickAddressId) && trim($pickAddressId) !== '') {
            return new PickupAddress(trim($pickAddressId), null, null, null);
        }

        $province = $this->text($origin->getProvince());
        $ward = $this->text($origin->getWard());
        $district = $this->text($origin->getDistrict());

        $regionId = $origin->getRegionId();
        if ($regionId !== null) {
            if ($ward === null) {
                return null;
            }

            // Shipping-origin-style origin: derive GHTK names from the canonical pipeline
            // (DEC-FEATYA2C0W-004) — province comes out of the normalization, not the raw origin.
            $normalized = $this->destinationResolver->resolve(
                (string) ($origin->getCountryId() ?? 'VN'),
                $regionId,
                null,
                $ward,
                $operation
            );

            return $normalized === null
                ? null // unresolvable pickup — strict hide, no best-guess request
                : new PickupAddress(null, $normalized->province, $normalized->district ?? $district, $normalized->ward);
        }

        if ($province === null || $ward === null) {
            return null;
        }

        return new PickupAddress(null, $province, $district, $ward);
    }

    private function text(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }
}
