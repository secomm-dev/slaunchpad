<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api;

/**
 * TASK-XXBN5X r1 (Phase E-SL0, SPIKE-WHHEZV §7) — a single shipping service level definition.
 *
 * ShippingCore owns the CONCEPT and contracts of a service level; the actual taxonomy
 * (EXPRESS/SAME_DAY/STANDARD, INSTANT/NEXT_DAY/ECONOMY, a single STANDARD, …) is defined by
 * project/composition modules through registration — it is NEVER hardcoded here. ShippingCore
 * is a reusable foundation and must not embed Launchpad-specific business defaults.
 *
 * Machine code is the stable identity — references survive label changes, and labels are
 * configurable presentation ("code = EXPRESS, label = Giao nhanh 2 giờ"). The definition carries
 * NO SLA semantics and NO fallback-policy fields: those belong to project configuration and the
 * future fallback orchestration respectively.
 */
interface ShippingServiceLevelInterface
{
    /** Stable machine identity (e.g. "EXPRESS") — never a display label. */
    public function getCode(): string;

    /** Configurable presentation label for the storefront/admin (non-empty). */
    public function getLabel(): string;

    /** Whether this service level is currently offered/active in composition. */
    public function isEnabled(): bool;

    /** Display/orchestration ordering hint (lower = earlier). */
    public function getSortOrder(): int;
}
