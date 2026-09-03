<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Import\Hierarchy;

/**
 * FEAT-2PZQKJ / TASK-ADT94K — counters + row-level errors of a hierarchy import run.
 * Pure value object: the importer fills it, the caller (CLI / data patch) prints or persists it.
 */
class HierarchyImportResult
{
    /** @var array<int, array{row_index: int|null, code: string, reason: string}> */
    private array $errors = [];

    private int $rowsValidated = 0;
    private int $regionsInserted = 0;
    private int $regionsUpdated = 0;
    private int $citiesInserted = 0;
    private int $citiesUpdated = 0;

    public function addError(?int $rowIndex, string $code, string $reason): void
    {
        $this->errors[] = ['row_index' => $rowIndex, 'code' => $code, 'reason' => $reason];
    }

    public function setRowsValidated(int $count): void
    {
        $this->rowsValidated = $count;
    }

    public function countRegionInserted(): void
    {
        $this->regionsInserted++;
    }

    public function countRegionUpdated(): void
    {
        $this->regionsUpdated++;
    }

    public function countCityInserted(): void
    {
        $this->citiesInserted++;
    }

    public function countCityUpdated(): void
    {
        $this->citiesUpdated++;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<int, array{row_index: int|null, code: string, reason: string}> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getRowsValidated(): int
    {
        return $this->rowsValidated;
    }

    public function getRegionsInserted(): int
    {
        return $this->regionsInserted;
    }

    public function getRegionsUpdated(): int
    {
        return $this->regionsUpdated;
    }

    public function getCitiesInserted(): int
    {
        return $this->citiesInserted;
    }

    public function getCitiesUpdated(): int
    {
        return $this->citiesUpdated;
    }

    /**
     * @return array<string, mixed> plain report for CLI output / evidence logs
     */
    public function toArray(): array
    {
        return [
            'rows_validated' => $this->rowsValidated,
            'regions_inserted' => $this->regionsInserted,
            'regions_updated' => $this->regionsUpdated,
            'cities_inserted' => $this->citiesInserted,
            'cities_updated' => $this->citiesUpdated,
            'errors' => $this->errors,
        ];
    }
}
