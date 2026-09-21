<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\GhtkAddressOverrideImport;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Secomm\Ghtk\Model\Address\GhtkAddressAdapter;

/**
 * Hardened GHTK address OVERRIDE CSV importer (replace-all, all-or-nothing)
 * (DEC-TASK7AJ3K8-002 — exception rows only; the full administrative dataset is NEVER
 * imported: canonical name_vi is the default representation).
 *
 * 1. Parse + strip UTF-8 BOM (CsvReader).
 * 2. Validate the WHOLE file first (canonical checks via VietNamAddress contracts);
 *    any structural/canonical error aborts without touching the DB.
 * 3. On a clean file, a single transaction performs the replace-all:
 *    insertOnDuplicate for every CSV row (insert/update) + delete the rows whose
 *    canonical key is absent from the CSV.
 * 4. Invalidate the address-adapter cache and write an audit log line.
 */
class Importer
{
    private const TABLE = 'secomm_ghtk_address_map';

    /**
     * Columns updated on duplicate (canonical key columns are never overwritten).
     */
    private const UPDATE_COLUMNS = ['ghtk_province', 'ghtk_district', 'ghtk_ward', 'is_active', 'note'];

    public function __construct(
        private CsvReader $csvReader,
        private Validator $validator,
        private ResourceConnection $resourceConnection,
        private CacheInterface $cache,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Replace-all import. Never throws — failures are reported on the Summary.
     */
    public function import(string $filePath, string $adminUser = ''): Summary
    {
        $summary = new Summary();

        try {
            $rows = $this->csvReader->read($filePath);
        } catch (LocalizedException $e) {
            return $summary->addError($e->getMessage())->setFailed(1);
        }

        $result = $this->validator->validate($rows);
        $summary->incrementSkipped($result['skipped']);

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                $summary->addError($error);
            }

            return $summary->setFailed(count($result['errors'])); // all-or-nothing: no DB change.
        }

        $accepted = $result['accepted'];

        try {
            $this->replace($accepted, $summary, $adminUser);
        } catch (\Throwable $e) {
            $summary->addError(
                __('Import failed and was rolled back: %1', $e->getMessage())->render()
            )->setFailed(1);
            $this->logger->error(
                'GHTK address override import transaction failed (rolled back).',
                ['admin' => $adminUser, 'exception' => $e->getMessage()]
            );
        }

        return $summary;
    }

    /**
     * @param array<int, array<string, mixed>> $accepted
     * @throws \Throwable
     */
    private function replace(array $accepted, Summary $summary, string $adminUser): void
    {
        $conn = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $existingByKey = [];
        foreach ($conn->fetchAll(
            $conn->select()->from($table, ['map_id', 'scheme_code', 'province_code', 'ward_code'])
        ) as $row) {
            $key = $row['scheme_code'] . '|' . $row['province_code'] . '|' . $row['ward_code'];
            $existingByKey[$key] = (int) $row['map_id'];
        }

        $desiredRows = [];
        $desiredKeys = [];
        foreach ($accepted as $entry) {
            $key = $entry['scheme_code'] . '|' . $entry['province_code'] . '|' . $entry['ward_code'];
            $desiredKeys[$key] = true;
            $desiredRows[] = $entry;
        }

        $insertedCount = 0;
        $updatedCount = 0;
        foreach (array_keys($desiredKeys) as $key) {
            if (isset($existingByKey[$key])) {
                $updatedCount++;
            } else {
                $insertedCount++;
            }
        }

        $removedIds = [];
        foreach ($existingByKey as $key => $mapId) {
            if (!isset($desiredKeys[$key])) {
                $removedIds[] = $mapId;
            }
        }

        $conn->beginTransaction();
        try {
            if (!empty($removedIds)) {
                $conn->delete($table, ['map_id' => ['in' => $removedIds]]);
            }
            if (!empty($desiredRows)) {
                $conn->insertOnDuplicate($table, $desiredRows, self::UPDATE_COLUMNS);
            }
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        $summary
            ->incrementInserted($insertedCount)
            ->incrementUpdated($updatedCount)
            ->incrementRemoved(count($removedIds))
            ->markCommitted();

        $this->cache->clean([GhtkAddressAdapter::CACHE_TAG]);

        $this->logger->info(
            'GHTK address override replace-all committed.',
            [
                'admin' => $adminUser,
                'before' => count($existingByKey),
                'after' => count($existingByKey) - count($removedIds) + $insertedCount,
                'inserted' => $insertedCount,
                'updated' => $updatedCount,
                'removed' => count($removedIds),
                'skipped' => $summary->getSkipped(),
            ]
        );
    }
}
