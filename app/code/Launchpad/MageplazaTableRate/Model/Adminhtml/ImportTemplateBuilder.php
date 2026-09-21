<?php
/*
 * TASK-JZXM66 — TableRate import template CSV content: EXACT importer column superset plus a
 * trailing informational `city_name` column, pre-filled with a worked example row (real city)
 * and a wildcard demonstration row.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Model\Adminhtml;

use Launchpad\MageplazaTableRate\Model\MptablerateImport;
use Magento\Framework\App\ResourceConnection;

/**
 * The template header comes from `MptablerateImport::templateColumns()` — importer schema and
 * template can never drift apart. The example row uses a REAL city code from the requested
 * region (default: the first VN region that has coded nodes), so a fill-in-and-import round
 * actually validates. `city_name` is display-only: the importer tolerates the extra column
 * and strips it during normalization.
 */
class ImportTemplateBuilder
{
    private const EXAMPLE_REGION_COUNTRY = 'VN';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CsvBuilder $csvBuilder
    ) {
    }

    /**
     * Full download content (BOM + header + example rows) for the requested region filter.
     *
     * @param int|null $regionId null = prefill from the first VN region with coded nodes
     */
    public function build(?int $regionId = null): string
    {
        return $this->csvBuilder->toCsvString(array_merge([MptablerateImport::templateColumns()], $this->buildRows($regionId)));
    }

    /**
     * @return array<int, array<int, string>> example rows (header NOT included). Without any
     *         coded city node in the database, only the wildcard demonstration row is written.
     */
    public function buildRows(?int $regionId = null): array
    {
        $example = $this->fetchExampleCity($regionId);
        $wildcardRow = $this->templateRow([
            'name' => 'Example rate (wildcard)',
            'country_id' => '*',
            'region' => '*',
            'city_code' => '',
            'city_name' => '',
        ]);

        if ($example === null) {
            return [$wildcardRow];
        }

        return [
            $this->templateRow([
                'name' => 'Example rate',
                'country_id' => $example['country_id'],
                'region' => $example['region_id'],
                'city_code' => $example['city_code'],
                'city_name' => $example['city_name'],
            ]),
            $wildcardRow,
        ];
    }

    /**
     * One full template row: sample pricing (importable as-is) with the given overrides.
     *
     * @param array<string, string> $overrides
     * @return array<int, string>
     */
    private function templateRow(array $overrides): array
    {
        $sample = [
            'name' => 'Example rate',
            'country_id' => self::EXAMPLE_REGION_COUNTRY,
            'region' => '*',
            'weight_from' => '0',
            'weight_to' => '10',
            'subtotal_from' => '0',
            'subtotal_to' => '999999999',
            'qty_from' => '0',
            'qty_to' => '100',
            'product_fixed_rate' => '0',
            'product_percentage_rate' => '0',
            'weight_fixed_rate' => '0',
            'order_fixed_rate' => '0',
            'delivery' => '2',
            'postcode' => '',
            'postcode_from' => '',
            'postcode_to' => '',
            'shipping_group' => '',
            'city_code' => '',
            'city_name' => '',
        ];

        $row = array_merge($sample, $overrides);

        return array_map(
            static fn (string $column): string => (string) ($row[$column] ?? ''),
            MptablerateImport::templateColumns()
        );
    }

    /**
     * First coded city node of the requested region (or the first VN region that has one),
     * with its region/country context for a self-validating example row.
     *
     * @return array{city_code: string, city_name: string, region_id: string, country_id: string}|null
     */
    private function fetchExampleCity(?int $regionId): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                ['city' => $this->resourceConnection->getTableName('directory_region_city')],
                [
                    'city_code' => 'city.code',
                    'city_name' => 'city.default_name',
                    'region_id' => 'city.region_id',
                    'country_id' => 'region.country_id',
                ]
            )
            ->joinLeft(
                ['region' => $this->resourceConnection->getTableName('directory_country_region')],
                'city.region_id = region.region_id',
                []
            )
            ->where('city.code IS NOT NULL')
            ->where('city.code <> ?', '')
            ->order('city.city_id ASC')
            ->limit(1);

        if ($regionId !== null && $regionId > 0) {
            $select->where('city.region_id = ?', $regionId);
        } else {
            $select->where('region.country_id = ?', self::EXAMPLE_REGION_COUNTRY);
        }

        $row = $connection->fetchRow($select);
        if (!$row) {
            return null;
        }

        return [
            'city_code' => (string) $row['city_code'],
            'city_name' => (string) $row['city_name'],
            'region_id' => (string) $row['region_id'],
            'country_id' => (string) ($row['country_id'] ?? self::EXAMPLE_REGION_COUNTRY),
        ];
    }
}
