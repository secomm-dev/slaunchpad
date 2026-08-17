<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\GhtkAddressMapImport;

/**
 * Validates parsed CSV rows BEFORE any database write (all-or-nothing).
 *
 * Structural errors (missing required field, bad type, bad country code) are fatal
 * — their presence causes the Importer to abort without touching the table.
 * Duplicate canonical keys within the file are non-fatal: the first occurrence wins
 * and later ones are counted as skipped.
 *
 * @return array{errors: string[], accepted: array<int, array<string, mixed>>, skipped: int}
 */
class Validator
{
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

            $countryId = $this->normalizeCountryId($row['country_id'] ?? '');
            $regionId = $this->normalizeInt($row['region_id'] ?? '');
            $wardId = $this->normalizeInt($row['ward_id'] ?? '');
            $ghtkProvince = trim((string) ($row['ghtk_province'] ?? ''));
            $ghtkDistrict = trim((string) ($row['ghtk_district'] ?? ''));
            $ghtkWard = trim((string) ($row['ghtk_ward'] ?? ''));
            $isActive = $this->normalizeActive($row['is_active'] ?? '');

            if ($countryId === null) {
                $errors[] = __('Line %1: country_id must be a 2-letter code.', $lineNo)->render();
                continue;
            }
            if ($regionId === null || $regionId < 1) {
                $errors[] = __('Line %1: region_id must be a positive integer.', $lineNo)->render();
                continue;
            }
            if ($wardId === null || $wardId < 1) {
                $errors[] = __('Line %1: ward_id must be a positive integer.', $lineNo)->render();
                continue;
            }
            if ($ghtkProvince === '') {
                $errors[] = __('Line %1: ghtk_province is required.', $lineNo)->render();
                continue;
            }
            if ($ghtkWard === '') {
                $errors[] = __('Line %1: ghtk_ward is required.', $lineNo)->render();
                continue;
            }
            if ($isActive === null) {
                $errors[] = __('Line %1: is_active must be 0 or 1.', $lineNo)->render();
                continue;
            }

            $key = $countryId . '|' . $regionId . '|' . $wardId;
            if (isset($seenKeys[$key])) {
                $skipped++;
                continue;
            }
            $seenKeys[$key] = true;

            $accepted[] = [
                'country_id' => $countryId,
                'region_id' => $regionId,
                'ward_id' => $wardId,
                'ghtk_province' => $ghtkProvince,
                'ghtk_district' => $ghtkDistrict !== '' ? $ghtkDistrict : null,
                'ghtk_ward' => $ghtkWard,
                'is_active' => $isActive,
            ];
        }

        return ['errors' => $errors, 'accepted' => $accepted, 'skipped' => $skipped];
    }

    private function normalizeCountryId(string $value): ?string
    {
        $value = strtoupper(trim($value));

        return preg_match('/^[A-Z]{2}$/', $value) ? $value : null;
    }

    private function normalizeInt(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        return (int) $value;
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
