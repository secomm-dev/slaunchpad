<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Shipment;

use Secomm\ShippingCore\Api\Physical\CarrierPhysicalLimitInterface;

/**
 * TASK-9Q5ZAK r2 (DEC-TASK9Q5ZAK-001) — GHN's physical package limits, per the current official
 * Create Order contract (developer.ghn.vn, re-verified against
 * `.ai/evidence/TASK-FMBBSD/ghn-api-contract-matrix.md` §5 lines 95-97):
 *
 *   weight (root AND per-item for heavy)  max 50,000 g
 *   length / width / height               max 200 cm per side
 *   service_type_id = 5 covers >=20kg AND multi-parcel → multiple physical packages ship as ONE
 *   GHN order with items[] per package (multi-package supported)
 *
 * Values are displayed by the admin package-information block; the ENFORCING validation stays in
 * {@see GhnPhysicalParcelInterpreter} (fail-closed INVALID_PARCEL before any HTTP call).
 */
final class GhnPhysicalLimit implements CarrierPhysicalLimitInterface
{
    public const MAX_WEIGHT_G = 50000;
    public const MAX_SIDE_CM = 200;

    public function getMaxPackageWeightG(): int
    {
        return self::MAX_WEIGHT_G;
    }

    public function getMaxLengthCm(): int
    {
        return self::MAX_SIDE_CM;
    }

    public function getMaxWidthCm(): int
    {
        return self::MAX_SIDE_CM;
    }

    public function getMaxHeightCm(): int
    {
        return self::MAX_SIDE_CM;
    }

    public function supportsMultiplePackages(): bool
    {
        return true;
    }
}
