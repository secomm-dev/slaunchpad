<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Api\Data;

/**
 * DEC-FEATYA2C0W-003 / TASK-9394A9 — one historical unit reference row
 * (secomm_vietnam_address_unit): immutable scheme-scoped code + display data.
 */
interface VnAddressUnitInterface
{
    public const SCHEME_CODE = 'scheme_code';
    public const CODE = 'code';
    public const PARENT_CODE = 'parent_code';
    public const REGION_CODE = 'region_code';
    public const LEVEL = 'level';
    public const NAME_VI = 'name_vi';
    public const NAME_EN = 'name_en';

    public function getSchemeCode(): string;

    public function getCode(): string;

    public function getParentCode(): ?string;

    public function getRegionCode(): string;

    /** 1 = region, 2 = first sub-level, 3 = second sub-level */
    public function getLevel(): int;

    public function getNameVi(): string;

    public function getNameEn(): string;
}
