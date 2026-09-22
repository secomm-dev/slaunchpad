<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model\Import;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * DEC-FEATYA2C0W-003 / TASK-9394A9 — writes the historical unit snapshot
 * (secomm_vietnam_address_unit) on EVERY import: the table accumulates every scheme
 * ever imported and is never touched by runtime swaps. Codes are immutable
 * (UNIQUE(scheme_code, code) upsert updates display data only); no DB-generated
 * runtime ids (city_id/region_id) ever enter this table — it stays portable.
 *
 * Region rows are units too (level 1) so scheme-pair mappings can address regions.
 *
 * BUG-ZTGGYZ (U1, TASK-FMBBSD) — the HIERARCHY contract: `parent_code` is the canonical
 * portable parent relation and MUST be complete for every non-root unit. The seed CSVs
 * leave the province edge implicit for direct-province children (2025 wards, PRE_2025
 * districts ship with an empty parent_code), so the writer SYNTHESISES it from the
 * region attribution: parent_code = region_code — valid because a region unit's code
 * equals its region_code. Level still follows the RAW seed parent (empty → 2, set → 3):
 * a synthesised province edge must NOT promote a 2025 ward to level 3. Existing DBs are
 * repaired by HierarchyParentBackfill (same relation) without touching any identity.
 */
class UnitSnapshotWriter
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @param array<int, array<string, string|int>> $regionRows derived region rows (reader)
     * @param array<int, array<string, string|int>> $unitRows dataset unit rows (reader)
     * @return int number of rows written (regions + units)
     * @throws LocalizedException when a unit cites a region absent from the dataset batch
     */
    public function write(string $scheme, array $regionRows, array $unitRows): int
    {
        VnSchemes::assertKnown($scheme);

        $regionUnitCodes = [];
        foreach ($regionRows as $row) {
            $regionUnitCodes[(string)$row['region_code']] = true;
        }

        $rows = [];
        foreach ($regionRows as $row) {
            $rows[] = [
                'scheme_code' => $scheme,
                'code' => (string)$row['region_code'],
                'parent_code' => null,
                'region_code' => (string)$row['region_code'],
                'level' => 1,
                'name_vi' => (string)$row['name_vi'],
                'name_en' => (string)$row['name_en'],
            ];
        }
        foreach ($unitRows as $row) {
            $csvParent = (string)$row['parent_code'];
            $regionCode = (string)$row['region_code'];
            if ($csvParent !== '') {
                $parentCode = $csvParent;
                $level = 3;
            } else {
                // Direct-province child: the seed leaves the province edge implicit —
                // synthesise it from the region attribution (region unit code == region_code).
                if (!isset($regionUnitCodes[$regionCode])) {
                    throw new LocalizedException(__(
                        'Dataset unit "%1" references region "%2" which is absent from the dataset regions.',
                        (string)$row['code'],
                        $regionCode
                    ));
                }
                $parentCode = $regionCode;
                $level = 2;
            }

            $rows[] = [
                'scheme_code' => $scheme,
                'code' => (string)$row['code'],
                'parent_code' => $parentCode,
                'region_code' => $regionCode,
                'level' => $level,
                'name_vi' => (string)$row['name_vi'],
                'name_en' => (string)$row['name_en'],
            ];
        }

        $connection = $this->connection();
        $table = $this->resource->getTableName('secomm_vietnam_address_unit');
        foreach (array_chunk($rows, self::BATCH_SIZE) as $batch) {
            $connection->insertOnDuplicate(
                $table,
                $batch,
                ['parent_code', 'region_code', 'level', 'name_vi', 'name_en']
            );
        }

        return count($rows);
    }

    private function connection(): \Magento\Framework\DB\Adapter\AdapterInterface
    {
        return $this->resource->getConnection();
    }
}
