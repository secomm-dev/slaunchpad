<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Physical;

/**
 * DEC-TASK9Q5ZAK-001 — the physical package limits ONE carrier enforces, for admin display and
 * pre-submit validation UX. ShippingCore never hard-codes values: each carrier module implements
 * this for its own numbers. FINAL provider-specific validation remains in the carrier adapter —
 * this contract exists so the admin UI can render limits without knowing why they exist.
 */
interface CarrierPhysicalLimitInterface
{
    public function getMaxPackageWeightG(): int;

    public function getMaxLengthCm(): int;

    public function getMaxWidthCm(): int;

    public function getMaxHeightCm(): int;

    public function supportsMultiplePackages(): bool;
}
