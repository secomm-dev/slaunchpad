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
 * DEC-FEATYA2C0W-003 / TASK-9394A9 — writes the historical unit snapshot
 * (secomm_vietnam_address_unit) on EVERY import: the table accumulates every scheme
 * ever imported and is never touched by runtime swaps. Codes are immutable
 * (UNIQUE(scheme_code, code) upsert updates display data only); no DB-generated
 * runtime ids (city_id/region_id) ever enter this table — it stays portable.
 *
 * Region rows are units too (level 1) so scheme-pair mappings can address regions.
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
     */
    public function write(string $scheme, array $regionRows, array $unitRows): int
    {
        VnSchemes::assertKnown($scheme);

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
            $rows[] = [
                'scheme_code' => $scheme,
                'code' => (string)$row['code'],
                'parent_code' => (string)$row['parent_code'] !== '' ? (string)$row['parent_code'] : null,
                'region_code' => (string)$row['region_code'],
                'level' => (string)$row['parent_code'] !== '' ? 3 : 2,
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
