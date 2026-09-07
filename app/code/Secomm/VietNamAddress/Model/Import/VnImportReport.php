<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model\Import;

/**
 * TASK-ADT94K — counters of one VN scheme import / dry-run run (swap model,
 * DEC-FEATYA2C0W-002). Printed by the CLI command and stored as evidence.
 */
class VnImportReport
{
    public bool $dryRun = false;

    public string $scheme = '';
    /** @var string[] */
    public array $installedSchemes = [];
    public bool $swapPerformed = false;
    public bool $rebuildPerformed = false;
    public int $unitsPurged = 0;

    /** TASK-F9XJ5G — reference-only import: runtime directory/config untouched. */
    public bool $referenceOnly = false;
    /** TASK-F9XJ5G — registry status after a reference-only import (CURRENT kept | HISTORICAL). */
    public string $registryStatus = '';

    public int $regionRowsValidated = 0;
    public int $unitRowsValidated = 0;
    public int $regionsInserted = 0;
    public int $regionsUpdated = 0;
    public int $citiesInserted = 0;
    public int $citiesUpdated = 0;
    public int $unitsSnapshoted = 0;

    public int $rekeyRegionMatched = 0;
    public int $rekeyRegionMissed = 0;
    public int $rekeyMatched = 0;
    public int $rekeyMissed = 0;
    public int $staleRemoved = 0;
    public int $staleRegionsRemoved = 0;
    public int $orphanMembershipRemoved = 0;

    public int $purgedRegions = 0;
    public int $purgedCities = 0;
    public int $purgedMembership = 0;

    /** @var array<int, string> "[GuardName] message" — dry-run only (DEC-FEATYA2C0W-004 D7). */
    public array $guardViolations = [];

    public int $membershipRows = 0;
    public bool $configUpdated = false;

    /** @var array<int, string> */
    public array $warnings = [];

    /** @var array<int, string> */
    public array $errors = [];

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scheme' => $this->scheme,
            'dry_run' => $this->dryRun,
            'installed_schemes' => $this->installedSchemes,
            'swap_performed' => $this->swapPerformed,
            'rebuild_performed' => $this->rebuildPerformed,
            'units_purged' => $this->unitsPurged,
            'reference_only' => $this->referenceOnly,
            'registry_status' => $this->registryStatus,
            'region_rows_validated' => $this->regionRowsValidated,
            'unit_rows_validated' => $this->unitRowsValidated,
            'regions_inserted' => $this->regionsInserted,
            'regions_updated' => $this->regionsUpdated,
            'cities_inserted' => $this->citiesInserted,
            'cities_updated' => $this->citiesUpdated,
            'units_snapshoted' => $this->unitsSnapshoted,
            'rekey_region_matched' => $this->rekeyRegionMatched,
            'rekey_region_missed' => $this->rekeyRegionMissed,
            'rekey_matched' => $this->rekeyMatched,
            'rekey_missed' => $this->rekeyMissed,
            'stale_removed' => $this->staleRemoved,
            'stale_regions_removed' => $this->staleRegionsRemoved,
            'orphan_membership_removed' => $this->orphanMembershipRemoved,
            'purged_regions' => $this->purgedRegions,
            'purged_cities' => $this->purgedCities,
            'purged_membership' => $this->purgedMembership,
            'guard_violations' => $this->guardViolations,
            'membership_rows' => $this->membershipRows,
            'config_updated' => $this->configUpdated,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
        ];
    }
}
