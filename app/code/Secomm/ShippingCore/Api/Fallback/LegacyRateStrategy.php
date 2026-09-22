<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Fallback;

use Magento\Framework\Exception\LocalizedException;

/**
 * TASK-5JQYMP (architecture Revision v5 §15.1, DEC-FEATYA2C0W-005) — provider-neutral legacy
 * RATE strategy identities for carriers whose RATE/fee operation depends on the legacy
 * PRE-2025 administrative scheme while the storefront runs VN_ADMIN_2025.
 *
 * Per carrier + RATE operation only — never a carrier-level disable; CREATE/CANCEL/TRACK are
 * unaffected. Strategy configuration is composition/carrier-owned (consumer-supplied); this
 * class is only the ShippingCore-owned identity of the strategies.
 *
 * IMPLEMENTATION NAMING NOTE (DEC-FEATYA2C0W-005 addendum): architecture wording §15.1 calls
 * the skip-mapping strategy "FALLBACK_ONLY" — the constant is named DIRECT_FALLBACK here to
 * avoid colliding with the Launchpad bridge operational mode FALLBACK_ONLY (§18). Semantics are
 * identical; the bridge mode is a separate concept (Mageplaza checkout exposure).
 */
final class LegacyRateStrategy
{
    /**
     * Skip legacy mapping, external resolver and the carrier RATE API entirely — the service
     * level contribution is directly fallback-eligible (LEGACY_ADDRESS_FALLBACK).
     * (Architecture §15.1 wording: "FALLBACK_ONLY".)
     */
    public const DIRECT_FALLBACK = 'DIRECT_FALLBACK';

    /**
     * Attempt local 2025→PRE-2025 mapping first: unique → carrier RATE API; AMBIGUOUS →
     * external resolver selector or fallback-eligible; UNMAPPED → fallback-eligible
     * (resolver P1 is never called for UNMAPPED).
     */
    public const MAP_THEN_FALLBACK = 'MAP_THEN_FALLBACK';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [self::DIRECT_FALLBACK, self::MAP_THEN_FALLBACK];
    }

    public static function exists(string $strategy): bool
    {
        return in_array($strategy, self::all(), true);
    }

    /**
     * @throws LocalizedException unknown legacy RATE strategy
     */
    public static function assertKnown(string $strategy): void
    {
        if (!self::exists($strategy)) {
            throw new LocalizedException(
                __('Unknown legacy RATE strategy "%1". Known strategies: %2.', $strategy, implode(', ', self::all()))
            );
        }
    }
}
