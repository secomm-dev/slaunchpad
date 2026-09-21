<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Rate;

use Magento\Framework\Exception\LocalizedException;

/**
 * TASK-MD2BD3 (architecture v10 §35.3) — Rate Source Mode per carrier RATE operation.
 *
 * - CARRIER_ONLY: eligible → carrier RATE → no valid rate → KHÔNG fallback.
 * - CARRIER_WITH_FALLBACK (Launchpad default): carrier RATE trước → fallback khi failure/state
 *   fallback-eligible.
 * - FALLBACK_ONLY: skip resolution/mapping/RATE API → normal fallback orchestration. Vẫn respects
 *   CarrierEligibility (destination ngoài scope → unavailable, không giả eligibility).
 *
 * KHÔNG liên quan Bridge Operational Mode FALLBACK_ONLY/STANDALONE của
 * `Launchpad_MageplazaTableRate` (đó là Mageplaza checkout exposure — scope riêng).
 */
final class RateSourceMode
{
    public const CARRIER_ONLY = 'CARRIER_ONLY';
    public const CARRIER_WITH_FALLBACK = 'CARRIER_WITH_FALLBACK';
    public const FALLBACK_ONLY = 'FALLBACK_ONLY';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [self::CARRIER_ONLY, self::CARRIER_WITH_FALLBACK, self::FALLBACK_ONLY];
    }

    public static function exists(string $mode): bool
    {
        return in_array($mode, self::all(), true);
    }

    /**
     * @throws LocalizedException unknown rate source mode
     */
    public static function assertKnown(string $mode): void
    {
        if (!self::exists($mode)) {
            throw new LocalizedException(
                __('Unknown rate source mode "%1". Known modes: %2.', $mode, implode(', ', self::all()))
            );
        }
    }
}
