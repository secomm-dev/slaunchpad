<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Api\Data;

/**
 * GHTK address mapping entity.
 *
 * Canonical key: (country_id, region_id, ward_id). ward_id references
 * Secomm_AddressDropdown directory_region_city.city_id (the VN 2-level ward level; stable).
 * The source_* name columns are display/audit/import support only.
 */
interface GhtkAddressMapInterface
{
    public const MAP_ID = 'map_id';
    public const COUNTRY_ID = 'country_id';
    public const REGION_ID = 'region_id';
    public const WARD_ID = 'ward_id';
    public const SOURCE_PROVINCE_NAME = 'source_province_name';
    public const SOURCE_WARD_NAME = 'source_ward_name';
    public const GHTK_PROVINCE = 'ghtk_province';
    public const GHTK_DISTRICT = 'ghtk_district';
    public const GHTK_WARD = 'ghtk_ward';
    public const IS_ACTIVE = 'is_active';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public function getMapId(): ?int;
    public function setMapId(?int $mapId): self;

    public function getCountryId(): string;
    public function setCountryId(string $countryId): self;

    public function getRegionId(): int;
    public function setRegionId(int $regionId): self;

    public function getWardId(): int;
    public function setWardId(int $wardId): self;

    public function getSourceProvinceName(): ?string;
    public function setSourceProvinceName(?string $name): self;

    public function getSourceWardName(): ?string;
    public function setSourceWardName(?string $name): self;

    public function getGhtkProvince(): string;
    public function setGhtkProvince(string $province): self;

    public function getGhtkDistrict(): ?string;
    public function setGhtkDistrict(?string $district): self;

    public function getGhtkWard(): string;
    public function setGhtkWard(string $ward): self;

    public function isActive(): bool;
    public function setIsActive(bool $isActive): self;

    public function getCreatedAt(): ?string;
    public function setCreatedAt(?string $createdAt): self;

    public function getUpdatedAt(): ?string;
    public function setUpdatedAt(?string $updatedAt): self;
}
