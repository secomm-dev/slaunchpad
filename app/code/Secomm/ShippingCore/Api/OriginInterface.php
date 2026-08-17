<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api;

/**
 * Normalized runtime shipping origin (SL-015 / DEC-SL015-001): the address a
 * shipment physically leaves from (warehouse / store / MSI source).
 *
 * Vietnam 2-level address model (DEC-020/025): province = Magento region,
 * ward = native city level. district is NULLABLE — it is not a required field
 * of the Launchpad address model. regionId is preserved alongside province so
 * carriers can normalize names via the canonical (region_id, ward_id) key.
 *
 * Carrier-specific extension data rides in metadata under a dotted
 * "{carrierCode}.{key}" key (e.g. "ghtk.pick_address_id", "ghn.shop_id") —
 * the shared DTO never hard-codes carrier fields.
 */
interface OriginInterface
{
    public function getSourceCode(): ?string;

    public function getCountryId(): ?string;

    public function getRegionId(): ?int;

    public function getProvince(): ?string;

    public function getDistrict(): ?string;

    public function getWard(): ?string;

    public function getStreet(): ?string;

    public function getPostcode(): ?string;

    public function getTelephone(): ?string;

    public function getContactName(): ?string;

    /**
     * @param string $key Dotted key, e.g. "ghtk.pick_address_id".
     * @param mixed $default Returned when the key is absent.
     * @return mixed
     */
    public function getMetadata(string $key, mixed $default = null): mixed;

    public function hasMetadata(string $key): bool;
}
