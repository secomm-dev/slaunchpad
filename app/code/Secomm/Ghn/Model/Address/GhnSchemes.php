<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address;

use Magento\Framework\Exception\LocalizedException;

/**
 * SPEC-FEAT-FQWEQ3 §3/§5 — the two GHN administrative address schemes stored in
 * secomm_ghn_address_unit. GHN keeps two address models in parallel:
 *
 *  - GHN_ADMIN_2025: Province → Ward (current model; master-data API v3; the `name` values are
 *    what Create Order needs with is_new_to_address=true — SPEC §3.1/§17).
 *  - GHN_ADMIN_PRE_2025: Province → District → Ward (legacy model; still required by Calculate
 *    Fee and Leadtime via district_id + ward_code — SPEC §3.2/§12).
 *
 * Identity rule (§5): one row = one GHN unit in ONE scheme. Never a merged current/legacy shape.
 */
final class GhnSchemes
{
    public const GHN_ADMIN_2025 = 'GHN_ADMIN_2025';
    public const GHN_ADMIN_PRE_2025 = 'GHN_ADMIN_PRE_2025';

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_DISABLED = 'DISABLED';

    /** @var array<string, int> scheme → maximum unit depth (1 = province level) */
    private const SCHEMES = [
        self::GHN_ADMIN_2025 => 2,
        self::GHN_ADMIN_PRE_2025 => 3,
    ];

    /**
     * @return array<string, int>
     */
    public static function all(): array
    {
        return self::SCHEMES;
    }

    public static function exists(string $scheme): bool
    {
        return isset(self::SCHEMES[$scheme]);
    }

    /**
     * Maximum unit depth for a scheme (2 = province → ward, 3 = province → district → ward).
     */
    public static function maxDepth(string $scheme): int
    {
        self::assertKnown($scheme);

        return self::SCHEMES[$scheme];
    }

    /**
     * @throws LocalizedException unknown GHN scheme code
     */
    public static function assertKnown(string $scheme): void
    {
        if (!self::exists($scheme)) {
            throw new LocalizedException(
                __('Unknown GHN address scheme "%1". Known schemes: %2.', $scheme, implode(', ', array_keys(self::SCHEMES)))
            );
        }
    }
}
