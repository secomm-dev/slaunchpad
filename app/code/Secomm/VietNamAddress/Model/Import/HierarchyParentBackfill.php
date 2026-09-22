<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model\Import;

use Magento\Framework\App\ResourceConnection;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * BUG-ZTGGYZ (U1, TASK-FMBBSD) — one-shot repair for unit snapshots written BEFORE the
 * writer synthesised the province edge: backfills `parent_code = region_code` for
 * direct-province children (level 2) whose parent_code is NULL.
 *
 * Contract (same as UnitSnapshotWriter): `parent_code` is the canonical portable parent
 * relation; a region unit's code equals its region_code, so the join against level-1
 * region units both validates the target and skips orphans deterministically.
 *
 * Safety properties: idempotent (only NULL parents are touched — re-runs are no-ops),
 * identity-preserving (scheme_code / code / names / region_code / level never change),
 * no runtime-table involvement and no scheme swap.
 */
class HierarchyParentBackfill
{
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * Backfill one scheme's level-2 rows.
     *
     * @return array{backfilled: int, remainingNull: int, orphan: int}
     */
    public function backfill(string $scheme): array
    {
        VnSchemes::assertKnown($scheme);
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('secomm_vietnam_address_unit');

        // Join against the same scheme's level-1 region unit (code == region_code): rows
        // without a matching region are orphans and are left untouched for reporting.
        $backfilled = (int) $connection->query(
            "UPDATE {$table} AS u "
            . "INNER JOIN {$table} AS r ON r.scheme_code = u.scheme_code AND r.code = u.region_code AND r.level = 1 "
            . 'SET u.parent_code = u.region_code '
            . 'WHERE u.scheme_code = ? AND u.level = 2 AND u.parent_code IS NULL',
            [$scheme]
        )->rowCount();

        $remainingNull = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM {$table} WHERE scheme_code = ? AND level = 2 AND parent_code IS NULL",
            [$scheme]
        );
        $orphan = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM {$table} AS u "
            . 'LEFT JOIN ' . "{$table} AS r ON r.scheme_code = u.scheme_code AND r.code = u.region_code AND r.level = 1 "
            . 'WHERE u.scheme_code = ? AND u.level = 2 AND u.parent_code IS NULL AND r.code IS NULL',
            [$scheme]
        );

        return ['backfilled' => $backfilled, 'remainingNull' => $remainingNull, 'orphan' => $orphan];
    }

    /**
     * Backfill every known scheme that has snapshot rows.
     *
     * @return array<string, array{backfilled: int, remainingNull: int, orphan: int}>
     */
    public function backfillAll(): array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('secomm_vietnam_address_unit');
        $schemes = $connection->fetchCol(
            "SELECT DISTINCT scheme_code FROM {$table} WHERE level = 2 AND parent_code IS NULL"
        );

        $report = [];
        foreach ($schemes as $scheme) {
            if (!VnSchemes::exists((string) $scheme)) {
                continue;
            }
            $report[(string) $scheme] = $this->backfill((string) $scheme);
        }

        return $report;
    }
}
