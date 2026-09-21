<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Mapping;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Exception\LocalizedException;
use Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-6TNKDH — canonical unit source for the GHN mapping lifecycle, read straight from the
 * Secomm_VietNamAddress shipped dataset CSVs (directive §2: the shipped files are the SSOT for
 * mapping authoring; read-only — that module is never modified by this feature).
 *
 * Why not the DB-backed provider: the seeded secomm_vietnam_address_unit rows carry
 * parent_code = NULL on every child level (2025 wards / PRE districts), so
 * getChildren(scheme, '') can never return them — a VietNamAddress-side defect recorded for its
 * owning stream (proven live 2026-09-11: audit enumeration returned 0 canonical units for both
 * schemes). The shipped CSVs are parent-complete under the documented profile rules:
 *   - parent_code populated              → that code is the parent;
 *   - parent_code empty, region row      → root (returned by getChildren(scheme, ''));
 *   - parent_code empty, non-region row  → parent = region_code (2025 wards / PRE districts).
 *
 * PRE_2025 resolves to the end-of-2024 SNAPSHOT file: mapping dataset v1.0.0 (TASK-6TNKDH
 * phase 2) is keyed to VN_ADMIN_PRE_2025_SNAPSHOT_2024 codes and the reference DB layer was
 * migrated to the same snapshot by RefreshVnAdminPre2025Snapshot2024 (TASK-GS78X2). The plain
 * legacy unit file is the pre-snapshot set — 232 snapshot codes do not exist there.
 */
class CanonicalCsvProvider implements VnAddressUnitProviderInterface
{
    /**
     * Scheme → shipped canonical dataset file. Snapshot-aware per the docblock; schemes not
     * listed fall back to VnSchemes::unitFile().
     */
    private const CANONICAL_SOURCE_FILES = [
        'VN_ADMIN_PRE_2025' => 'VN_ADMIN_PRE_2025_SNAPSHOT_2024_import.csv',
    ];

    /** @var array<string, array<string, CanonicalUnit>> scheme_code => code => unit */
    private array $unitsByScheme = [];

    /** @var array<string, array<string, list<CanonicalUnit>>> scheme_code => parent_code => children */
    private array $childrenByScheme = [];

    public function __construct(
        private readonly ComponentRegistrar $componentRegistrar
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getUnit(string $schemeCode, string $code): ?VnAddressUnitInterface
    {
        return $this->load($schemeCode)[$code] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getChildren(string $schemeCode, string $parentCode): array
    {
        return $this->childrenIndex($schemeCode)[$parentCode] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function countByScheme(string $schemeCode): int
    {
        return count($this->load($schemeCode));
    }

    /**
     * @return array<string, CanonicalUnit>
     * @throws LocalizedException unknown scheme / unreadable dataset
     */
    private function load(string $schemeCode): array
    {
        if (isset($this->unitsByScheme[$schemeCode])) {
            return $this->unitsByScheme[$schemeCode];
        }

        VnSchemes::assertKnown($schemeCode);
        $file = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, 'Secomm_VietNamAddress')
            . '/Files/' . (self::CANONICAL_SOURCE_FILES[$schemeCode] ?? VnSchemes::unitFile($schemeCode));
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw new LocalizedException(__('Canonical dataset %1 is not readable.', $file));
        }

        $units = [];
        $regions = []; // The dataset files carry NO explicit region rows — regions are synthesized
                       // from the distinct region_code/region_name columns (same as VnDatasetReader).
        try {
            $header = fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null] || $row === [] || count($row) < 7) {
                    continue;
                }
                $code = trim((string) $row[3]);
                $regionCode = trim((string) $row[0]);
                $regionName = trim((string) $row[1]);
                $regions[$regionCode] ??= $regionName;
                $parentCode = $this->deriveParentCode($code, trim((string) $row[4]), $regionCode);
                $unit = new CanonicalUnit(
                    $schemeCode,
                    $code,
                    $parentCode,
                    $regionCode,
                    $parentCode !== null && $parentCode !== $regionCode ? 3 : 2,
                    trim((string) $row[5]),                              // name_vi
                    trim((string) $row[6])                               // name_en
                );
                $units[$unit->getCode()] = $unit;
            }
        } finally {
            fclose($handle);
        }

        foreach ($regions as $regionCode => $regionName) {
            $units[$regionCode] = new CanonicalUnit(
                $schemeCode,
                $regionCode,
                null,
                $regionCode,
                1,
                $regionName,
                $regionName
            );
        }

        return $this->unitsByScheme[$schemeCode] = $units;
    }

    /**
     * @return array<string, list<CanonicalUnit>>
     * @throws LocalizedException
     */
    private function childrenIndex(string $schemeCode): array
    {
        if (isset($this->childrenByScheme[$schemeCode])) {
            return $this->childrenByScheme[$schemeCode];
        }

        $index = [];
        foreach ($this->load($schemeCode) as $unit) {
            $index[$unit->getParentCode() ?? ''][] = $unit;
        }

        return $this->childrenByScheme[$schemeCode] = $index;
    }

    private function deriveParentCode(string $code, string $parentCode, string $regionCode): ?string
    {
        if ($parentCode !== '') {
            return $parentCode;
        }

        // Region rows are the root; every other level falls back to its region (2025 wards,
        // PRE districts).
        return preg_match(VnSchemes::REGION_CODE_PATTERN, $code) === 1 ? null : $regionCode;
    }
}
