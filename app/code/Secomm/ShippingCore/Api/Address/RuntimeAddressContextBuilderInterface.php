<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Address;

/**
 * TASK-7AJ3K8 (Phase E-C1) — builds a resolution context from SCALAR runtime address data
 * (the shape every Magento address carrier actually holds: RateRequest destination fields,
 * sales/order addresses — none of which are Quote\Address instances).
 *
 * Source canonical identity comes exclusively from Secomm_VietNamAddress: id-based
 * (regionId + cityId) when a city/ward node id is known, name-based (regionId + cityName)
 * otherwise — the transitional D5 entry. An AMBIGUOUS name match yields NO identity but
 * carries the candidate codes in the context; the manager surfaces them as AMBIGUOUS —
 * candidates are never auto-picked (DEC-FEATYA2C0W-004 D9).
 *
 * Translation ONLY — the canonical graph is resolved downstream by the resolution manager;
 * the carrier-required target scheme is applied HERE, once, from the capability (E-C0 rule).
 */
interface RuntimeAddressContextBuilderInterface
{
    /**
     * @param string|null $countryId ISO country id (non-VN destinations bypass resolution downstream)
     * @param int $regionId Magento region PK (0 = unknown)
     * @param int|null $cityId Magento city/ward node PK when known (native "city_id" data, rarely persisted)
     * @param string|null $cityName locality name as carried by the NATIVE city field (the
     *                              AddressDropdown engine stores the active locality level there);
     *                              used for the name-based bridge when $cityId is absent
     * @param CarrierAddressCapabilityInterface $capability carrier address capability (required scheme)
     * @param string|null $streetText shipping street text (external disambiguation hint; never a lookup key)
     */
    public function build(
        ?string $countryId,
        int $regionId,
        ?int $cityId,
        ?string $cityName,
        CarrierAddressCapabilityInterface $capability,
        ?string $streetText = null
    ): ShippingAddressResolutionContextInterface;
}
