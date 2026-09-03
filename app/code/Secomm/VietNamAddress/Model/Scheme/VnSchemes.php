<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model\Scheme;

use Magento\Framework\Exception\LocalizedException;

/**
 * DEC-FEATYA2C0W-003 — the versioned Vietnam administrative scheme catalog.
 *
 * Scheme codes (VN_ADMIN_2025, VN_ADMIN_PRE_2025, future VN_ADMIN_<year>) are IMMUTABLE
 * identities: the year is part of the identity and never changes meaning. CURRENT /
 * HISTORICAL / FUTURE are STATUS labels (registry column), never identities.
 *
 * Unit code prefixes (VNA25-… / VNAP25-…) are dataset-supplied facts kept here for
 * validation only — the scheme is always taken from explicit context, NEVER parsed
 * from a code prefix.
 *
 * legacy_profile_aliases maps the pre-DEC-003 profile codes (vn_current / vn_legacy)
 * onto canonical schemes so already-seeded membership/config upgrade as a same-scheme
 * refresh (no purge, city_id preserved).
 */
final class VnSchemes
{
    public const VN_ADMIN_2025 = 'VN_ADMIN_2025';
    public const VN_ADMIN_PRE_2025 = 'VN_ADMIN_PRE_2025';

    public const STATUS_CURRENT = 'CURRENT';
    public const STATUS_HISTORICAL = 'HISTORICAL';
    public const STATUS_FUTURE = 'FUTURE';

    /**
     * Canonical dataset region identity: "VN-" + the 2-digit official code, e.g. "VN-01".
     * Dataset-supplied (alphabetical sequence per scheme), shared by every scheme — the
     * bare 2-digit legacy codes (directory code_region from VN_Address_2Level.csv, official
     * government numbering) are a DIFFERENT numbering and never appear in the datasets.
     */
    public const REGION_CODE_PATTERN = '/^VN-\d{2}$/';

    public const XML_PATH_ACTIVE_SCHEME = 'secomm_vietnam_address/general/active_scheme';

    /**
     * Catalog entries; shape per @see self::catalog().
     */
    private const CATALOG = [
        self::VN_ADMIN_2025 => [
            'profile_code' => 'vn_admin_2025',
            'label' => 'VN Admin 2025 (2-level)',
            'level_count' => 2,
            'unit_file' => 'VN_ADMIN_2025_import.csv',
            'code_pattern' => '/^VNA25-[0-9A-F]{10}$/',
            'counts' => ['regions' => 34, 'depth1' => 3321, 'depth2' => 0],
            'collision' => null,
            'legacy_profile_aliases' => ['vn_current'],
        ],
        self::VN_ADMIN_PRE_2025 => [
            'profile_code' => 'vn_admin_pre_2025',
            'label' => 'VN Admin pre-2025 (3-level)',
            'level_count' => 3,
            'unit_file' => 'VN_ADMIN_PRE_2025_import.csv',
            'code_pattern' => '/^VNAP25-[0-9A-F]{10}$/',
            'counts' => ['regions' => 63, 'depth1' => 699, 'depth2' => 10595],
            'collision' => ['groups' => 19, 'rows' => 38],
            'legacy_profile_aliases' => ['vn_legacy'],
        ],
    ];

    /**
     * @return array<string, array{profile_code: string, label: string, level_count: int, unit_file: string, code_pattern: string, counts: array{regions: int, depth1: int, depth2: int}, collision: array{groups: int, rows: int}|null, legacy_profile_aliases: string[]}>
     */
    public static function catalog(): array
    {
        return self::CATALOG;
    }

    public static function exists(string $scheme): bool
    {
        return isset(self::CATALOG[$scheme]);
    }

    /**
     * @throws LocalizedException unknown scheme code
     */
    public static function assertKnown(string $scheme): void
    {
        if (!self::exists($scheme)) {
            throw new LocalizedException(
                __('Unknown Vietnam administrative scheme "%1". Known schemes: %2.', $scheme, implode(', ', array_keys(self::CATALOG)))
            );
        }
    }

    /**
     * Profile code rendering the scheme (address_profiles.xml, renamed per DEC-003).
     */
    public static function profileCode(string $scheme): string
    {
        return self::entry($scheme)['profile_code'];
    }

    public static function label(string $scheme): string
    {
        return self::entry($scheme)['label'];
    }

    public static function unitFile(string $scheme): string
    {
        return self::entry($scheme)['unit_file'];
    }

    public static function codePattern(string $scheme): string
    {
        return self::entry($scheme)['code_pattern'];
    }

    /**
     * Canonical scheme for a profile code, resolving the pre-DEC-003 aliases
     * (vn_current → VN_ADMIN_2025, vn_legacy → VN_ADMIN_PRE_2025).
     */
    public static function schemeForProfile(string $profileCode): ?string
    {
        foreach (self::CATALOG as $scheme => $entry) {
            if ($entry['profile_code'] === $profileCode || in_array($profileCode, $entry['legacy_profile_aliases'], true)) {
                return $scheme;
            }
        }

        return null;
    }

    /**
     * @return array{profile_code: string, label: string, level_count: int, unit_file: string, code_pattern: string, counts: array{regions: int, depth1: int, depth2: int}, collision: array{groups: int, rows: int}|null, legacy_profile_aliases: string[]}
     */
    private static function entry(string $scheme): array
    {
        self::assertKnown($scheme);

        return self::CATALOG[$scheme];
    }
}
