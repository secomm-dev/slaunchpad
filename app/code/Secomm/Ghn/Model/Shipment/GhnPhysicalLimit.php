<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Shipment;

use Secomm\Ghn\Model\Config;
use Secomm\Ghn\Model\GhnShipmentConstraints;
use Secomm\ShippingCore\Api\Physical\CarrierPhysicalLimitInterface;

/**
 * TASK-9Q5ZAK r2 (DEC-TASK9Q5ZAK-001) — GHN's physical package limits, per the current official
 * Create Order contract (developer.ghn.vn, re-verified against
 * `.ai/evidence/TASK-FMBBSD/ghn-api-contract-matrix.md` §5 lines 95-97):
 *
 *   weight (root AND per-item for heavy)  max 50,000 g
 *   length / width / height               max 200 cm per dimension
 *   service_type_id = 5 covers >=20kg total AND multi-package CREATE payloads → multiple
 *   physical packages ship as ONE GHN order with items[] per package (CREATE semantics —
 *   deliberately NOT the RATE total-weight-only rule, TASK-WNQCRW)
 *
 * Values are displayed by the admin package-information block; the ENFORCING validation stays in
 * {@see GhnPhysicalParcelInterpreter} (fail-closed INVALID_PARCEL before any HTTP call).
 */
final class GhnPhysicalLimit implements CarrierPhysicalLimitInterface
{
    public const MAX_WEIGHT_G = GhnShipmentConstraints::TYPE_5_MAX_WEIGHT_G;
    /** TASK-ZS2B41 — constants remain the AUTHORITATIVE DEFAULTS; system config may override. */
    public const MAX_SIDE_CM = GhnShipmentConstraints::MAX_SIDE_CM;

    public function __construct(
        private readonly Config $config
    ) {
    }

    public function getMaxPackageWeightG(): int
    {
        return self::MAX_WEIGHT_G;
    }

    /**
     * TASK-ZS2B41 (rev. 3-path, 2026-10-01) — per-dimension limits (cm) are merchant-tunable
     * via system config SHARED with RATE (per LENGTH/WIDTH/HEIGHT); authoritative default =
     * {@see MAX_SIDE_CM} (Create contract 200) when the config is empty/invalid.
     */
    public function getMaxLengthCm(): int
    {
        return $this->config->getMaxLengthCm();
    }

    public function getMaxWidthCm(): int
    {
        return $this->config->getMaxWidthCm();
    }

    public function getMaxHeightCm(): int
    {
        return $this->config->getMaxHeightCm();
    }

    public function supportsMultiplePackages(): bool
    {
        return true;
    }
}
