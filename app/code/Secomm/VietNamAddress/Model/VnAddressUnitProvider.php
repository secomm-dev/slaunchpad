<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\VietNamAddress\Model\Data\VnAddressUnitData;

/**
 * TASK-9394A9 — parameterized read access to secomm_vietnam_address_unit.
 */
class VnAddressUnitProvider implements VnAddressUnitProviderInterface
{
    private const TABLE = 'secomm_vietnam_address_unit';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getUnit(string $schemeCode, string $code): ?VnAddressUnitInterface
    {
        $row = $this->connection()->fetchRow(
            $this->connection()->select()
                ->from($this->table(self::TABLE))
                ->where('scheme_code = ?', $schemeCode)
                ->where('code = ?', $code)
        );

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @inheritDoc
     */
    public function getChildren(string $schemeCode, string $parentCode): array
    {
        $rows = $this->connection()->fetchAll(
            $this->connection()->select()
                ->from($this->table(self::TABLE))
                ->where('scheme_code = ?', $schemeCode)
                ->where('parent_code = ?', $parentCode)
                ->order('name_vi ASC')
        );

        return array_map(fn (array $row): VnAddressUnitInterface => $this->hydrate($row), $rows);
    }

    /**
     * @inheritDoc
     */
    public function countByScheme(string $schemeCode): int
    {
        return (int)$this->connection()->fetchOne(
            $this->connection()->select()
                ->from($this->table(self::TABLE), ['COUNT(*)'])
                ->where('scheme_code = ?', $schemeCode)
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): VnAddressUnitInterface
    {
        return new VnAddressUnitData(
            (string)$row['scheme_code'],
            (string)$row['code'],
            $row['parent_code'] === null ? null : (string)$row['parent_code'],
            (string)$row['region_code'],
            (int)$row['level'],
            (string)$row['name_vi'],
            (string)$row['name_en']
        );
    }

    private function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }

    private function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }
}
