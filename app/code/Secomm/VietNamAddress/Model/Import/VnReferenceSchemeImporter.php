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
 * TASK-F9XJ5G — reference-only scheme import (DEC-FEATYA2C0W-003 §4): populate a scheme's
 * historical canonical units + registry row WITHOUT touching the runtime directory, so a
 * historical dataset (e.g. VN_ADMIN_PRE_2025) can be imported while VN_ADMIN_2025 stays
 * the active runtime scheme.
 *
 *   read + validate (STOP_ON_ERROR — a violation writes nothing)
 *     → unit snapshot upsert (secomm_vietnam_address_unit — accumulates across schemes,
 *       identity scheme_code + code, never purged here)
 *     → registry upsert (secomm_vietnam_address_scheme) — status HISTORICAL; the scheme's
 *       own CURRENT status is kept (a reference import never demotes, see
 *       SchemeRegistryUpdater::applyReference)
 *
 * ONE transaction: a failure leaves no partial unit snapshot and no partial registry row.
 * Deliberately NO dependency on the hierarchy import, config writer, membership, reference
 * guards or caches — the runtime directory tables, secomm_vietnam_address/general/
 * active_scheme and address/profiles/mapping are structurally unreachable from here.
 */
class VnReferenceSchemeImporter
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly VnDatasetReader $reader,
        private readonly VnDatasetValidator $validator,
        private readonly UnitSnapshotWriter $unitSnapshotWriter,
        private readonly SchemeRegistryUpdater $registryUpdater
    ) {
    }

    /**
     * @throws VnImportValidationException dataset contract violated — nothing was written
     * @throws \Exception DB fault — transaction rolled back, nothing partial remains
     */
    public function import(string $scheme): VnImportReport
    {
        VnSchemes::assertKnown($scheme);
        $report = new VnImportReport();
        $report->scheme = $scheme;
        $report->referenceOnly = true;
        $dataset = $this->readAndValidate($scheme, $report);

        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            $report->unitsSnapshoted = $this->unitSnapshotWriter->write($scheme, $dataset['regions'], $dataset['units']);
            $report->registryStatus = $this->registryUpdater->applyReference($scheme);
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        return $report;
    }

    /**
     * Validation + simulation only — no writes.
     */
    public function dryRun(string $scheme): VnImportReport
    {
        VnSchemes::assertKnown($scheme);
        $report = new VnImportReport();
        $report->scheme = $scheme;
        $report->referenceOnly = true;
        $report->dryRun = true;
        $this->readAndValidate($scheme, $report);

        return $report;
    }

    /**
     * @return array{regions: array, units: array, header: string}
     */
    private function readAndValidate(string $scheme, VnImportReport $report): array
    {
        $dataset = $this->reader->read($scheme);
        $errors = $this->validator->validate($scheme, $dataset['regions'], $dataset['units']);
        $report->regionRowsValidated = count($dataset['regions']);
        $report->unitRowsValidated = count($dataset['units']);

        if ($errors !== []) {
            $report->errors = $errors;
            throw new VnImportValidationException(
                __('VN dataset "%1" failed validation with %2 error(s); nothing was written.', $scheme, count($errors)),
                $errors
            );
        }

        return $dataset;
    }
}
