<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model\Import;

use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * DEC-FEATYA2C0W-003 — dataset contract validation. Guards against file drift: counts,
 * unit code patterns (per scheme), the shared canonical region code pattern (VN-XX),
 * uniqueness, hierarchy integrity, region coverage/consistency,
 * collision suffix preservation (19 groups / 38 rows on VN_ADMIN_PRE_2025), name hygiene.
 *
 * Pure checks only — no DB access, no writes. Returns row-addressed errors.
 *
 * Note: name_en MAY legitimately contain non-ASCII (ethnolinguistic minority names such as
 * "Kon Plông", "Đắk Blà") — only invisible/zero-width characters are rejected.
 */
class VnDatasetValidator
{
    /** Collision disambiguation suffix in the Vietnamese name, e.g. "Yên Viên (Thị trấn)". */
    private const COLLISION_SUFFIX_PATTERN = '/\s\((Thị trấn|Xã|Phường)\)$/u';
    /** Zero-width / invisible characters that must never appear in a display name. */
    private const INVISIBLE_CHARS = "\u{200B}\u{200C}\u{200D}\u{FEFF}";

    /**
     * @param array<int, array<string, string|int>> $regions region rows from VnDatasetReader::read()
     * @param array<int, array<string, string|int>> $units unit rows from VnDatasetReader::read()
     * @return array<int, string> error list; empty = dataset valid
     */
    public function validate(string $scheme, array $regions, array $units): array
    {
        VnSchemes::assertKnown($scheme);

        $errors = [];
        if ($regions === []) {
            $errors[] = 'No region rows: the 5-column header carries no region names — use the 7-column canonical format.';
        }

        $this->checkRegions($scheme, $regions, $errors);
        $this->checkUnits($scheme, $units, $errors);
        $this->checkCounts($scheme, $regions, $units, $errors);
        $this->checkCollisions($scheme, $units, $errors);

        return $errors;
    }

    /**
     * @param array<int, string> $errors
     */
    private function checkRegions(string $scheme, array $regions, array &$errors): void
    {
        $seen = [];
        foreach ($regions as $row) {
            $line = (int)$row['line'];
            if (!preg_match(VnSchemes::REGION_CODE_PATTERN, (string)$row['region_code'])) {
                $errors[] = sprintf(
                    'Line %d: region_code "%s" is not a canonical "VN-XX" region code.',
                    $line,
                    $row['region_code']
                );
            }
            if (isset($seen[$row['region_code']])) {
                $errors[] = sprintf('Line %d: duplicate region_code "%s".', $line, $row['region_code']);
            }
            $seen[(string)$row['region_code']] = true;

            foreach (['name_vi', 'name_en'] as $field) {
                if ((string)$row[$field] === '') {
                    $errors[] = sprintf('Line %d: region %s is empty.', $line, $field);
                }
                if (preg_match('/[' . self::INVISIBLE_CHARS . ']/u', (string)$row[$field])) {
                    $errors[] = sprintf('Line %d: region %s contains invisible/zero-width characters.', $line, $field);
                }
            }
        }
    }

    /**
     * @param array<int, string> $errors
     */
    private function checkUnits(string $scheme, array $units, array &$errors): void
    {
        $pattern = VnSchemes::codePattern($scheme);
        $seenCodes = [];
        $depth1 = [];
        $seenNames = [];

        foreach ($units as $row) {
            $line = (int)$row['line'];

            if ((string)$row['code'] === '') {
                $errors[] = sprintf('Line %d: empty code.', $line);
                continue;
            }
            if (!preg_match($pattern, (string)$row['code'])) {
                $errors[] = sprintf('Line %d: code "%s" does not match the %s pattern.', $line, $row['code'], $scheme);
            }
            if (isset($seenCodes[$row['code']])) {
                $errors[] = sprintf('Line %d: duplicate code "%s" (first at line %d).', $line, $row['code'], $seenCodes[$row['code']]);
            }
            $seenCodes[(string)$row['code']] = $line;

            foreach (['name_vi', 'name_en'] as $field) {
                if ((string)$row[$field] === '') {
                    $errors[] = sprintf('Line %d: empty %s.', $line, $field);
                }
                if (preg_match('/[' . self::INVISIBLE_CHARS . ']/u', (string)$row[$field])) {
                    $errors[] = sprintf('Line %d: %s contains invisible/zero-width characters.', $line, $field);
                }
            }

            $nameKey = $row['region_code'] . '|' . $row['parent_code'] . '|' . $row['name_vi'];
            if (isset($seenNames[$nameKey])) {
                $errors[] = sprintf(
                    'Line %d: duplicate (region, parent, name_vi) "%s" (first at line %d).',
                    $line,
                    $row['name_vi'],
                    $seenNames[$nameKey]
                );
            }
            $seenNames[$nameKey] = $line;

            if ((string)$row['parent_code'] === '') {
                $depth1[(string)$row['code']] = (string)$row['region_code'];
            } elseif ($scheme === VnSchemes::VN_ADMIN_2025) {
                $errors[] = sprintf('Line %d: %s must not contain depth-2 rows (parent_code "%s").', $line, $scheme, $row['parent_code']);
            }
        }

        foreach ($units as $row) {
            if ((string)$row['parent_code'] !== '') {
                $parentRegion = $depth1[$row['parent_code']] ?? null;
                if ($parentRegion === null) {
                    $errors[] = sprintf(
                        'Line %d: parent_code "%s" does not resolve to a depth-1 row.',
                        (int)$row['line'],
                        $row['parent_code']
                    );
                } elseif ($parentRegion !== (string)$row['region_code']) {
                    $errors[] = sprintf(
                        'Line %d: parent_code "%s" belongs to another region ("%s").',
                        (int)$row['line'],
                        $row['parent_code'],
                        $parentRegion
                    );
                }
            }
        }
    }

    /**
     * @param array<int, string> $errors
     */
    private function checkCounts(string $scheme, array $regions, array $units, array &$errors): void
    {
        $expected = VnSchemes::catalog()[$scheme]['counts'];

        $depth1 = 0;
        $depth2 = 0;
        foreach ($units as $row) {
            if ((string)$row['parent_code'] === '') {
                $depth1++;
            } else {
                $depth2++;
            }
        }

        if (count($regions) !== $expected['regions']) {
            $errors[] = sprintf('Region rows: expected %d, got %d.', $expected['regions'], count($regions));
        }
        if ($depth1 !== $expected['depth1']) {
            $errors[] = sprintf('Depth-1 rows: expected %d, got %d.', $expected['depth1'], $depth1);
        }
        if ($depth2 !== $expected['depth2']) {
            $errors[] = sprintf('Depth-2 rows: expected %d, got %d.', $expected['depth2'], $depth2);
        }

        // Region coverage: every unit's region_code must exist among the region rows.
        $regionCodes = array_flip(array_map(
            static fn (array $row): string => (string)$row['region_code'],
            $regions
        ));
        foreach ($units as $row) {
            if (!isset($regionCodes[(string)$row['region_code']])) {
                $errors[] = sprintf(
                    'Line %d: unit references undefined region_code "%s".',
                    (int)$row['line'],
                    $row['region_code']
                );
            }
        }
    }

    /**
     * Collision contract: VN_ADMIN_PRE_2025 keeps exactly 19 groups / 38 suffix rows; every
     * other scheme must be fully clean (no suffixes at all).
     *
     * @param array<int, string> $errors
     */
    private function checkCollisions(string $scheme, array $units, array &$errors): void
    {
        $expected = VnSchemes::catalog()[$scheme]['collision'];

        $groups = [];
        $suffixRows = 0;
        foreach ($units as $row) {
            if (preg_match(self::COLLISION_SUFFIX_PATTERN, (string)$row['name_vi']) !== 1) {
                continue;
            }
            $suffixRows++;
            if ($expected !== null) {
                $base = preg_replace(self::COLLISION_SUFFIX_PATTERN, '', (string)$row['name_vi']);
                $groups[$row['region_code'] . '|' . $row['parent_code'] . '|' . $base] = true;
            }
        }

        if ($expected === null) {
            if ($suffixRows !== 0) {
                $errors[] = sprintf('%s must not contain collision suffixes (%d found).', $scheme, $suffixRows);
            }

            return;
        }

        if ($suffixRows !== $expected['rows']) {
            $errors[] = sprintf('Collision rows: expected %d, got %d.', $expected['rows'], $suffixRows);
        }
        if (count($groups) !== $expected['groups']) {
            $errors[] = sprintf('Collision groups: expected %d, got %d.', $expected['groups'], count($groups));
        }
    }
}
