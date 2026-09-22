<?php
/*
 * TASK-JZXM66 — City Reference CSV content: the live `directory_region_city` identity list
 * (code + label + region + parent), generated from the database on demand for admins.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Model\Adminhtml;

use Magento\Framework\App\ResourceConnection;

/**
 * Reads `directory_region_city` (joined to `directory_country_region` for the country /
 * region columns, self-joined for the parent node) and renders the reference CSV. Identity is
 * the stable `code` — rows without a code have no stable identity and are never exported.
 */
class CityReferenceBuilder
{
    /** Exact CSV column contract (order is part of the contract). */
    public const CSV_COLUMNS = [
        'country_code',
        'region_code',
        'region_name',
        'city_code',
        'city_name',
        'parent_city_code',
        'parent_city_name',
    ];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CsvBuilder $csvBuilder
    ) {
    }

    /**
     * Full download content (BOM + header + data rows) for the requested region filter.
     *
     * @param int|null $regionId null = all regions
     */
    public function build(?int $regionId = null): string
    {
        return $this->csvBuilder->toCsvString($this->toCsvRows($this->fetchRows($regionId)));
    }

    /**
     * @return array<int, array<string, string|null>> raw DB rows (country/region/city/parent)
     */
    public function fetchRows(?int $regionId = null): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                ['city' => $this->resourceConnection->getTableName('directory_region_city')],
                [
                    'country_code' => 'region.country_id',
                    'region_code' => 'region.code',
                    'region_name' => 'region.default_name',
                    'city_code' => 'city.code',
                    'city_name' => 'city.default_name',
                    'parent_city_code' => 'parent.code',
                    'parent_city_name' => 'parent.default_name',
                ]
            )
            ->joinLeft(
                ['region' => $this->resourceConnection->getTableName('directory_country_region')],
                'city.region_id = region.region_id',
                []
            )
            ->joinLeft(
                ['parent' => $this->resourceConnection->getTableName('directory_region_city')],
                'city.parent_city_id = parent.city_id',
                []
            )
            ->where('city.code IS NOT NULL')
            ->where('city.code <> ?', '')
            ->order('region.default_name ASC')
            ->order('city.default_name ASC')
            ->order('city.city_id ASC');

        if ($regionId !== null && $regionId > 0) {
            $select->where('city.region_id = ?', $regionId);
        }

        return $connection->fetchAll($select);
    }

    /**
     * Pure transform (unit-tested without a DB): header first, then one CSV row per DB row.
     * Defensive re-check of the code presence — a row without a stable code is skipped.
     *
     * @param array<int, array<string, string|null>> $rows
     * @return array<int, array<int, string>> header row first
     */
    public function toCsvRows(array $rows): array
    {
        $csvRows = [self::CSV_COLUMNS];
        foreach ($rows as $row) {
            $cityCode = trim((string) ($row['city_code'] ?? ''));
            if ($cityCode === '') {
                continue;
            }

            $csvRows[] = [
                (string) ($row['country_code'] ?? ''),
                (string) ($row['region_code'] ?? ''),
                (string) ($row['region_name'] ?? ''),
                $cityCode,
                (string) ($row['city_name'] ?? ''),
                (string) ($row['parent_city_code'] ?? ''),
                (string) ($row['parent_city_name'] ?? ''),
            ];
        }

        return $csvRows;
    }
}
