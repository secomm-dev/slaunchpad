<?php
declare(strict_types=1);

namespace Secomm\AddressDropdown\Model\ResourceModel;

/**
 * TASK-Z6SK3T — canonical vi-alphabet ORDER BY body for the city/ward name sort.
 *
 * History: d8bad508 (TASK-7HVGAB) removed a REPLACE-based Đ→D normalization in favor of
 * the bare column collation — on the utf8mb4_general_ci baseline, 'Đ' (U+0110) sorts
 * after 'Z', pushing every Đ-prefixed ward to the bottom of option lists. The first
 * restoration (REPLACE Đ→D) interleaved Đ entries inside the D block; per user decision
 * 2026-10-02 the sort now uses MySQL's real Vietnamese collation instead: Đ is its own
 * letter AFTER the full D block (…D, Đ, E… — Vietnamese alphabet order), tone marks fold
 * (ai_ci), case-insensitive. Requires MySQL 8.0+ (utf8mb4_vi_0900_ai_ci; verified on the
 * 8.4 baseline).
 *
 * ONE shared builder — the sort sites (LocationHierarchyProvider, CityLocaleCollection,
 * CustomerData\CityData, Helper\Address) must never drift apart again.
 */
final class CitySort
{
    /**
     * The Vietnamese collation used for the sort key (MySQL 8.0+).
     */
    public const COLLATION = 'utf8mb4_vi_0900_ai_ci';

    /**
     * Build the canonical ORDER BY body for a city/ward name sort.
     *
     * @param string $nameSql localized name column reference (e.g. "n.name")
     * @param string $defaultSql default_name column reference (e.g. "c.default_name")
     * @param string $idSql id column reference used as tie-breaker (e.g. "c.city_id")
     * @return string ORDER BY body (ASC name-key, ASC id)
     */
    public static function expression(string $nameSql, string $defaultSql, string $idSql): string
    {
        return sprintf(
            'CONVERT(COALESCE(%s, %s) USING utf8mb4) COLLATE %s ASC, %s ASC',
            $nameSql,
            $defaultSql,
            self::COLLATION,
            $idSql
        );
    }
}
