<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/**
 * DB access for secomm_ghn_address_mapping (SPEC-FEAT-FQWEQ3 §6) — the canonical↔GHN bridge.
 * Writes go through MappingImporter (UPSERT of reviewed APPROVED rows only — DEC-FEATFQWEQ3-002:
 * no truncate, existing reviewed rows are never silently replaced by a sync or a generation run).
 */
class AddressMapping
{
    public const TABLE = 'secomm_ghn_address_mapping';

    private const UPSERT_COLUMNS = [
        'secomm_scheme_code',
        'secomm_unit_code',
        'ghn_address_unit_id',
        'mapping_method',
        'mapping_status',
        'verified_at',
    ];

    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function upsert(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $connection->getTableName(self::TABLE);

        $affected = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            $affected += (int) $connection->insertOnDuplicate($table, $chunk, self::UPSERT_COLUMNS);
        }

        return $affected;
    }

    /**
     * @return array<string, mixed>|null approved mapping row for the canonical unit
     */
    public function findApproved(string $secommScheme, string $unitCode): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($connection->getTableName(self::TABLE))
            ->where('secomm_scheme_code = ?', $secommScheme)
            ->where('secomm_unit_code = ?', $unitCode)
            ->where('mapping_status = ?', 'APPROVED');

        $row = $connection->fetchRow($select);

        return $row ?: null;
    }

    /**
     * @return array<string, array<string, mixed>> secomm_unit_code => mapping row for the scheme
     */
    public function fetchByScheme(string $secommScheme): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($connection->getTableName(self::TABLE))
            ->where('secomm_scheme_code = ?', $secommScheme);

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(string) $row['secomm_unit_code']] = $row;
        }

        return $result;
    }
}
