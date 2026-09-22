<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Secomm\Ghn\Model\Address\GhnSchemes;

/**
 * DB access for secomm_ghn_address_unit (SPEC-FEAT-FQWEQ3 §5). Parameterized SQL only; write
 * path = batch insertOnDuplicate keyed by UNIQUE(scheme_code, provider_key). Rows missing from a
 * sync are soft-DISABLED (never DELETEd) so mapping audits can report them (AC-ADDR-006).
 */
class AddressUnit
{
    public const TABLE = 'secomm_ghn_address_unit';

    private const UPSERT_COLUMNS = [
        'scheme_code',
        'provider_id',
        'provider_code',
        'provider_key',
        'parent_id',
        'depth',
        'name',
        'extension_names',
        'status',
        'source_version',
        'synced_at',
    ];

    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /**
     * Insert or update unit rows (batched by caller). Returns affected row count estimate.
     *
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
     * @return array<string, int> provider_key => entity_id for the scheme (all statuses)
     */
    public function fetchKeys(string $scheme): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($connection->getTableName(self::TABLE), ['provider_key', 'entity_id'])
            ->where('scheme_code = ?', $scheme);

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(string) $row['provider_key']] = (int) $row['entity_id'];
        }

        return $result;
    }

    /**
     * Soft-disable rows that disappeared from the latest master-data snapshot.
     *
     * @param array<string, true> $keepKeys provider_key => true
     */
    public function disableMissing(string $scheme, array $keepKeys, string $sourceVersion): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $connection->getTableName(self::TABLE);

        $keep = array_keys($keepKeys);
        if ($keep === []) {
            // Nothing fetched: disable everything of the scheme (defensive — full fetch failure is
            // guarded by the synchronizer before it reaches here).
            $where = ['scheme_code = ?' => $scheme, 'status = ?' => GhnSchemes::STATUS_ACTIVE];
        } else {
            $where = [
                'scheme_code = ?' => $scheme,
                'status = ?' => GhnSchemes::STATUS_ACTIVE,
                'provider_key NOT IN (?)' => $keep,
            ];
        }

        return (int) $connection->update(
            $table,
            ['status' => GhnSchemes::STATUS_DISABLED, 'source_version' => $sourceVersion],
            $where
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchUnit(int $entityId): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($connection->getTableName(self::TABLE))
            ->where('entity_id = ?', $entityId);

        $row = $connection->fetchRow($select);

        return $row ?: null;
    }

    /**
     * Stable-identity lookup (scheme + provider_key) — used by the mapping importer to validate
     * portable GHN identities without exposing local entity IDs to any file format.
     *
     * @return array<string, mixed>|null
     */
    public function fetchUnitByKey(string $scheme, string $providerKey): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($connection->getTableName(self::TABLE))
            ->where('scheme_code = ?', $scheme)
            ->where('provider_key = ?', $providerKey);

        $row = $connection->fetchRow($select);

        return $row ?: null;
    }

    /**
     * All units of a scheme, ordered parent-first (depth ASC, entity ASC) — consumed by the
     * mapping matcher and the audit report.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchByScheme(string $scheme, ?string $status = null): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($connection->getTableName(self::TABLE))
            ->where('scheme_code = ?', $scheme)
            ->order('depth ASC')
            ->order('entity_id ASC');
        if ($status !== null) {
            $select->where('status = ?', $status);
        }

        return $connection->fetchAll($select);
    }
}
