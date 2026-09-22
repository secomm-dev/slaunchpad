<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Api\Data;

/**
 * GHTK address OVERRIDE entity (DEC-TASK7AJ3K8-002 — r1 TEXT_NATIVE correction).
 *
 * Canonical Vietnamese names (name_vi, Secomm_VietNamAddress reference layer) are the DEFAULT
 * GHTK destination/pickup representation. A row here replaces them ONLY for units the GHTK API
 * demonstrably needs different text for — an exception/representation override, NEVER a source
 * of truth for VN administrative identity. Empty table = fully functional carrier.
 *
 * Canonical key: (scheme_code, province_code, ward_code) — stable across VN scheme swaps
 * (DEC-FEATYA2C0W-004 D6); no runtime directory references. At least one ghtk_* override value
 * must be non-null per row (import validator enforces).
 */
interface GhtkAddressOverrideInterface
{
    public const MAP_ID = 'map_id';
    public const SCHEME_CODE = 'scheme_code';
    public const PROVINCE_CODE = 'province_code';
    public const WARD_CODE = 'ward_code';
    public const GHTK_PROVINCE = 'ghtk_province';
    public const GHTK_DISTRICT = 'ghtk_district';
    public const GHTK_WARD = 'ghtk_ward';
    public const IS_ACTIVE = 'is_active';
    public const NOTE = 'note';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public function getMapId(): ?int;
    public function setMapId(?int $mapId): self;

    public function getSchemeCode(): string;
    public function setSchemeCode(string $schemeCode): self;

    public function getProvinceCode(): string;
    public function setProvinceCode(string $provinceCode): self;

    public function getWardCode(): string;
    public function setWardCode(string $wardCode): self;

    public function getGhtkProvince(): ?string;
    public function setGhtkProvince(?string $province): self;

    public function getGhtkDistrict(): ?string;
    public function setGhtkDistrict(?string $district): self;

    public function getGhtkWard(): ?string;
    public function setGhtkWard(?string $ward): self;

    public function isActive(): bool;
    public function setIsActive(bool $isActive): self;

    public function getNote(): ?string;
    public function setNote(?string $note): self;

    public function getCreatedAt(): ?string;
    public function setCreatedAt(?string $createdAt): self;

    public function getUpdatedAt(): ?string;
    public function setUpdatedAt(?string $updatedAt): self;
}
