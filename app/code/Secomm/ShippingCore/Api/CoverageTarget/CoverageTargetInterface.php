<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\CoverageTarget;

/**
 * TASK-WY6WP5 — minimal coverage target metadata (directive §2: type, code, label —
 * deliberately no capability matrix). A target being registered means only "this target
 * supports Shipping Coverage configuration"; it never implies a persisted coverage config.
 */
interface CoverageTargetInterface
{
    public function getIdentity(): CoverageTargetIdentity;

    /**
     * Admin display label, e.g. "GHN (Giao Hàng Nhanh)".
     */
    public function getLabel(): string;
}
