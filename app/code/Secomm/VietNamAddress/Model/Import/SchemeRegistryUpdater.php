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
 * DEC-FEATYA2C0W-003 / TASK-9394A9 — registry transition on a successful import:
 * the imported scheme becomes CURRENT, the previously current scheme becomes HISTORICAL
 * (its identity/code never changes — only the status label moves). FUTURE rows stay
 * untouched until their dataset is actually imported.
 *
 * TASK-F9XJ5G — reference-only variant: applyReference() upserts a scheme's registry row
 * WITHOUT the runtime promotion — status HISTORICAL, never demoting any scheme (its own
 * CURRENT status included).
 */
class SchemeRegistryUpdater
{
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Idempotent: re-importing a scheme keeps it CURRENT and re-syncs the catalog metadata.
     */
    public function apply(string $scheme): void
    {
        VnSchemes::assertKnown($scheme);
        $entry = VnSchemes::catalog()[$scheme];
        $connection = $this->connection();
        $table = $this->resource->getTableName('secomm_vietnam_address_scheme');

        $connection->beginTransaction();
        try {
            $connection->update(
                $table,
                ['status' => VnSchemes::STATUS_HISTORICAL],
                $connection->quoteInto('status = ?', VnSchemes::STATUS_CURRENT)
                    . ' AND ' . $connection->quoteInto('scheme_code <> ?', $scheme)
            );
            $connection->insertOnDuplicate(
                $table,
                [
                    'scheme_code' => $scheme,
                    'label' => $entry['label'],
                    'profile_code' => $entry['profile_code'],
                    'level_count' => $entry['level_count'],
                    'status' => VnSchemes::STATUS_CURRENT,
                ],
                ['label', 'profile_code', 'level_count', 'status']
            );
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    private function connection(): \Magento\Framework\DB\Adapter\AdapterInterface
    {
        return $this->resource->getConnection();
    }

    /**
     * TASK-F9XJ5G — reference-only import: upsert the catalog metadata and mark the
     * scheme HISTORICAL, WITHOUT the runtime promotion of apply(). Deterministic status
     * rule (no implicit transitions):
     *
     *   row missing | HISTORICAL | FUTURE  →  HISTORICAL
     *   row CURRENT                          →  CURRENT kept (only metadata refreshes)
     *
     * Other schemes' rows are never touched — a reference import never demotes the
     * active (CURRENT) scheme, whoever it is. Idempotent by the scheme_code PK upsert.
     *
     * @return string the status after the update (VnSchemes::STATUS_CURRENT kept, or STATUS_HISTORICAL)
     */
    public function applyReference(string $scheme): string
    {
        VnSchemes::assertKnown($scheme);
        $entry = VnSchemes::catalog()[$scheme];
        $connection = $this->connection();
        $table = $this->resource->getTableName('secomm_vietnam_address_scheme');

        $connection->beginTransaction();
        try {
            $existingStatus = $connection->fetchOne(
                $connection->select()
                    ->from($table, ['status'])
                    ->where('scheme_code = ?', $scheme)
                    ->limit(1)
            );
            $status = $existingStatus === VnSchemes::STATUS_CURRENT
                ? VnSchemes::STATUS_CURRENT
                : VnSchemes::STATUS_HISTORICAL;
            $connection->insertOnDuplicate(
                $table,
                [
                    'scheme_code' => $scheme,
                    'label' => $entry['label'],
                    'profile_code' => $entry['profile_code'],
                    'level_count' => $entry['level_count'],
                    'status' => $status,
                ],
                ['label', 'profile_code', 'level_count', 'status']
            );
            $connection->commit();

            return $status;
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }
}
