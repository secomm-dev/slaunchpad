<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Address;

use Magento\Framework\Exception\LocalizedException;

/**
 * TASK-MD2BD3 (architecture Revision v10 §35.4) — Address Resolution Policy cho carrier
 * operation yêu cầu legacy scheme khi canonical mapping AMBIGUOUS.
 *
 * Applicability: chỉ carrier `RATE` operation có `requiredScheme(RATE)` khác runtime canonical
 * scheme (cross-scheme mapping có thể AMBIGUOUS). Carrier RATE dùng current canonical identity →
 * not applicable. KHÔNG leak vào CREATE/CANCEL/TRACK. `FALLBACK_ONLY` (RateSourceMode) skip cả
 * policy evaluation.
 *
 * - STRICT: AMBIGUOUS → unavailable, không ambiguity-driven fallback.
 * - FALLBACK (Launchpad default): AMBIGUOUS → address-related fallback eligible.
 * - PICK_PRIMARY: deterministic curated-primary selection (Secomm_VietNamAddress) → carrier
 *   path; DATA_INTEGRITY_DEFECT (không curated designation) → không pick.
 *
 * Policy selection nằm ở carrier RATE configuration — KHÔNG hardcode carrier vào ShippingCore.
 */
final class AddressResolutionPolicy
{
    public const STRICT = 'STRICT';
    public const FALLBACK = 'FALLBACK';
    public const PICK_PRIMARY = 'PICK_PRIMARY';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [self::STRICT, self::FALLBACK, self::PICK_PRIMARY];
    }

    public static function exists(string $policy): bool
    {
        return in_array($policy, self::all(), true);
    }

    /**
     * @throws LocalizedException unknown address resolution policy
     */
    public static function assertKnown(string $policy): void
    {
        if (!self::exists($policy)) {
            throw new LocalizedException(
                __('Unknown address resolution policy "%1". Known policies: %2.', $policy, implode(', ', self::all()))
            );
        }
    }
}
