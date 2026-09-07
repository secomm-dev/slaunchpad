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

/**
 * TASK-J9AVGK — mapping import service: validate ALL rows first (STOP_ON_ERROR — nothing
 * written on any error), then upsert on the UNIQUE edge (idempotent re-runs).
 */
class VnMappingImporter
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly VnMappingReader $reader,
        private readonly VnMappingValidator $validator
    ) {
    }

    /**
     * @return array{rows_validated: int, errors: array<int, string>, warnings: array<int, string>}
     * @throws LocalizedException validation failure (nothing written)
     */
    public function import(string $path, bool $dryRun): array
    {
        $rows = $this->reader->read($path);
        ['errors' => $errors, 'warnings' => $warnings] = $this->validator->validate($rows);

        if ($errors !== []) {
            throw new VnImportValidationException(
                __('Mapping validation failed with %1 error(s); nothing was written.', count($errors)),
                $errors
            );
        }
        if ($dryRun) {
            return ['rows_validated' => count($rows), 'errors' => [], 'warnings' => $warnings];
        }

        $connection = $this->connection();
        $table = $this->resource->getTableName('secomm_vietnam_address_mapping');
        $batch = array_map(
            static fn (array $row): array => [
                'source_scheme' => (string)$row['source_scheme'],
                'source_code' => (string)$row['source_code'],
                'target_scheme' => (string)$row['target_scheme'],
                'target_code' => (string)$row['target_code'],
                'relation_type' => (string)$row['relation_type'],
            ],
            $rows
        );
        $connection->insertOnDuplicate($table, $batch, ['relation_type']);

        return ['rows_validated' => count($rows), 'errors' => [], 'warnings' => $warnings];
    }

    /**
     * DB-mode validation of already-imported rows (or a file via --file).
     *
     * @return array{errors: array<int, string>, warnings: array<int, string>}
     */
    public function validate(?string $path): array
    {
        if ($path !== null) {
            $rows = $this->reader->read($path);

            return $this->validator->validate($rows);
        }

        $connection = $this->connection();
        $rows = [];
        foreach ($connection->fetchAll(
            $connection->select()
                ->from($this->resource->getTableName('secomm_vietnam_address_mapping'))
                ->order('mapping_id ASC')
        ) as $index => $row) {
            $rows[] = [
                'line' => $index + 1,
                'source_scheme' => (string)$row['source_scheme'],
                'source_code' => (string)$row['source_code'],
                'target_scheme' => (string)$row['target_scheme'],
                'target_code' => (string)$row['target_code'],
                'relation_type' => (string)$row['relation_type'],
            ];
        }

        return $this->validator->validate($rows);
    }

    private function connection(): \Magento\Framework\DB\Adapter\AdapterInterface
    {
        return $this->resource->getConnection();
    }
}
