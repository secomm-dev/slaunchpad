<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\GhtkAddressOverrideImport;

use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * Validates parsed override CSV rows BEFORE any database write (all-or-nothing).
 * Canonical validation runs through Secomm_VietNamAddress CONTRACTS (unit reference layer)
 * — never raw SQL directory tables (DEC-TASK7AJ3K8-002).
 *
 * Per-row checks (fatal — any error aborts the import):
 *   valid scheme (VnSchemes catalog) · canonical province exists (region unit) ·
 *   canonical ward exists (sub-level unit) · ward belongs to province ·
 *   at least one ghtk_* override non-empty · is_active 0/1.
 * Duplicate canonical keys in-file are non-fatal: first wins, later ones skipped.
 *
 * @return array{errors: string[], accepted: array<int, array<string, mixed>>, skipped: int}
 */
class Validator
{
    /** Reference-layer level of region rows (ward overrides never target level 1). */
    private const LEVEL_REGION = 1;

    public function __construct(
        private readonly VnAddressUnitProviderInterface $unitProvider
    ) {
    }

    /**
     * @param array<int, array<string, string>> $rows
     */
    public function validate(array $rows): array
    {
        $errors = [];
        $accepted = [];
        $skipped = 0;
        $seenKeys = [];

        foreach ($rows as $index => $row) {
            $lineNo = $index + 2; // +1 for 0-based index, +1 for header row.

            $schemeCode = trim((string) ($row['scheme_code'] ?? ''));
            $provinceCode = trim((string) ($row['province_code'] ?? ''));
            $wardCode = trim((string) ($row['ward_code'] ?? ''));
            $ghtkProvince = trim((string) ($row['ghtk_province'] ?? ''));
            $ghtkDistrict = trim((string) ($row['ghtk_district'] ?? ''));
            $ghtkWard = trim((string) ($row['ghtk_ward'] ?? ''));
            $note = trim((string) ($row['note'] ?? ''));
            $isActive = $this->normalizeActive($row['is_active'] ?? '');

            if (!isset(VnSchemes::catalog()[$schemeCode])) {
                $errors[] = __('Line %1: scheme_code "%2" is not a known VN administrative scheme.', $lineNo, $schemeCode)->render();
                continue;
            }
            $province = $this->unitProvider->getUnit($schemeCode, $provinceCode);
            if ($province === null || $province->getLevel() !== self::LEVEL_REGION) {
                $errors[] = __('Line %1: province_code "%2" is not a canonical region unit of scheme %3.', $lineNo, $provinceCode, $schemeCode)->render();
                continue;
            }
            $ward = $this->unitProvider->getUnit($schemeCode, $wardCode);
            if ($ward === null || $ward->getLevel() === self::LEVEL_REGION) {
                $errors[] = __('Line %1: ward_code "%2" is not a canonical ward unit of scheme %3.', $lineNo, $wardCode, $schemeCode)->render();
                continue;
            }
            if ($ward->getRegionCode() !== $province->getRegionCode()) {
                $errors[] = __('Line %1: ward_code "%2" does not belong to province_code "%3".', $lineNo, $wardCode, $provinceCode)->render();
                continue;
            }
            if ($ghtkProvince === '' && $ghtkDistrict === '' && $ghtkWard === '') {
                $errors[] = __('Line %1: at least one ghtk_* override value is required — this table stores exceptions only, not a full dataset.', $lineNo)->render();
                continue;
            }
            if ($isActive === null) {
                $errors[] = __('Line %1: is_active must be 0 or 1.', $lineNo)->render();
                continue;
            }

            $key = $schemeCode . '|' . $provinceCode . '|' . $wardCode;
            if (isset($seenKeys[$key])) {
                $skipped++;
                continue;
            }
            $seenKeys[$key] = true;

            $accepted[] = [
                'scheme_code' => $schemeCode,
                'province_code' => $provinceCode,
                'ward_code' => $wardCode,
                'ghtk_province' => $ghtkProvince !== '' ? $ghtkProvince : null,
                'ghtk_district' => $ghtkDistrict !== '' ? $ghtkDistrict : null,
                'ghtk_ward' => $ghtkWard !== '' ? $ghtkWard : null,
                'is_active' => $isActive,
                'note' => $note !== '' ? $note : null,
            ];
        }

        return ['errors' => $errors, 'accepted' => $accepted, 'skipped' => $skipped];
    }

    private function normalizeActive(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return 1; // default active
        }

        return match ($value) {
            '0', '1' => (int) $value,
            default => null,
        };
    }
}
