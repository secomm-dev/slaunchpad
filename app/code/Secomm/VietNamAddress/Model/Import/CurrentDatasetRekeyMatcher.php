<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model\Import;

/**
 * TASK-ADT94K / DEC-FEATYA2C0W-003 — one-time bridge from the legacy-format data
 * (VN_Address_2Level.csv import: names carry administrative type words, codes are NULL)
 * to the supplied VN_ADMIN_2025 dataset codes (VNA25-…). Preserves city_id by matching on
 * normalised names, never on raw display names — identity stays with the code, the name
 * is display-only.
 *
 * Region-level bridge (buildRegionIndex/matchRegion): legacy regions carry bare official
 * codes ("01" = Hanoi) on a DIFFERENT numbering than the dataset "VN-XX" alphabetical
 * sequence (VN-01 = An Giang) — matched on normalised names instead, preserving region_id
 * (and with it the region_id of every re-keyed city row).
 *
 * Verified against the real datasets: the vi-name key (type prefix stripped, exact,
 * diacritic-sensitive) matches 3311/3311 units; the folded English key is only a fallback
 * (10 units fold to the same English string, e.g. Đồng Tiến / Đông Tiến → "dong tien").
 */
class CurrentDatasetRekeyMatcher
{
    /** Vietnamese administrative type prefixes still present in the old dataset. */
    private const VI_TYPE_PREFIX = '/^(Phường|Xã|Thị trấn|Thị xã|Quận|Huyện|Thành phố|Đặc khu)\s+/u';
    /** English type suffixes/prefixes still present in the old dataset (longest first). */
    private const EN_TYPE_SUFFIX = '/\s+(Special Economic Zone|Special Zone|Township|Commune|Ward|Town|District|City|Province|Society)$/u';
    private const EN_TYPE_PREFIX = '/^(Ward|Commune|Township)\s+/u';
    private const INVISIBLE_CHARS = "\u{200B}\u{200C}\u{200D}\u{FEFF}";

    /** @var array<string, array<string, string>> region_code => viKey => VNC code */
    private array $viIndex = [];
    /** @var array<string, array<string, array<int, string>>> region_code => enKey => VNC codes */
    private array $enIndex = [];
    /** @var array<string, array<int, string>> enKey => dataset region codes */
    private array $regionEnIndex = [];
    /** @var array<string, array<int, string>> viKey => dataset region codes */
    private array $regionViIndex = [];

    /**
     * Index the dataset side (cleaned VN_CURRENT city rows from the reader).
     *
     * @param array<int, array<string, string|int>> $datasetCityRows
     */
    public function buildIndex(array $datasetCityRows): void
    {
        $this->viIndex = [];
        $this->enIndex = [];

        foreach ($datasetCityRows as $row) {
            $region = (string)$row['region_code'];
            $code = (string)$row['code'];
            $this->viIndex[$region][$this->viKey((string)$row['name_vi'])] = $code;
            $this->enIndex[$region][$this->enKey((string)$row['name_en'])][] = $code;
        }
    }

    /**
     * Match one existing DB row (old-format vi name + English default_name) to a dataset
     * VNC code. Primary: exact vi key. Fallback: folded English key.
     *
     * @return string|null the VNC code, or null when no candidate exists
     * @throws \RuntimeException when the English fallback is ambiguous (folded duplicates)
     */
    public function match(string $regionCode, string $viName, string $defaultName): ?string
    {
        $viHit = $this->viIndex[$regionCode][$this->viKey($viName)] ?? null;
        if ($viHit !== null) {
            return $viHit;
        }

        $candidates = $this->enIndex[$regionCode][$this->enKey($defaultName)] ?? [];
        if ($candidates === []) {
            return null;
        }
        if (count($candidates) > 1) {
            throw new \RuntimeException(
                sprintf(
                    'Ambiguous re-key for region "%s", default_name "%s" folds to %d dataset codes (%s).',
                    $regionCode,
                    $defaultName,
                    count($candidates),
                    implode(', ', $candidates)
                )
            );
        }

        return $candidates[0];
    }

    /**
     * Index the dataset side for the REGION-level bridge: legacy directory regions carry
     * bare official codes ("01" = Hanoi) while datasets use "VN-XX" alphabetical sequence
     * codes — codes cannot be transformed into each other, so regions are matched on
     * normalised names (legacy names carry type words: "Hanoi City" / "Thành phố Hà Nội").
     *
     * @param array<int, array<string, string|int>> $datasetRegionRows derived region rows (reader)
     */
    public function buildRegionIndex(array $datasetRegionRows): void
    {
        $this->regionEnIndex = [];
        $this->regionViIndex = [];

        foreach ($datasetRegionRows as $row) {
            $code = (string)$row['region_code'];
            $this->regionEnIndex[$this->enKey((string)$row['name_en'])][] = $code;
            $this->regionViIndex[$this->viKey((string)$row['name_vi'])][] = $code;
        }
    }

    /**
     * Match one existing DB region row to a dataset region code. Primary: folded English
     * key (legacy default_name, e.g. "Hai Phong City" → "hai phong"); fallback: vi key
     * (legacy vi names carry type prefixes, e.g. "Thành phố Hà Nội" → "Hà Nội").
     *
     * @return string|null the dataset region code, or null when no candidate exists
     * @throws \RuntimeException when a key folds to several dataset codes
     */
    public function matchRegion(string $defaultName, string $viName): ?string
    {
        return $this->singleRegionCandidate($this->regionEnIndex[$this->enKey($defaultName)] ?? [], 'default_name', $defaultName)
            ?? $this->singleRegionCandidate($this->regionViIndex[$this->viKey($viName)] ?? [], 'vi name', $viName);
    }

    /**
     * @param array<int, string> $candidates
     */
    private function singleRegionCandidate(array $candidates, string $label, string $name): ?string
    {
        if ($candidates === []) {
            return null;
        }
        if (count($candidates) > 1) {
            throw new \RuntimeException(
                sprintf(
                    'Ambiguous region re-key: %s "%s" folds to %d dataset region codes (%s).',
                    $label,
                    $name,
                    count($candidates),
                    implode(', ', $candidates)
                )
            );
        }

        return $candidates[0];
    }

    /**
     * Dataset / DB vi-name key: NFC-normalise, strip invisible characters and one leading
     * administrative type prefix, trim. Exact, diacritic-sensitive. (The installed DB holds
     * a mix of NFC and NFD rows — normalising here keeps matching deterministic.)
     */
    public function viKey(string $viName): string
    {
        $name = $this->normalizeUnicode($viName);
        $name = $this->stripInvisible($name);
        $name = preg_replace(self::VI_TYPE_PREFIX, '', $name) ?? $name;

        return trim($name);
    }

    /**
     * Dataset / DB English key: NFC-normalise, strip invisible characters and type words
     * (suffix then prefix), lowercase, collapse whitespace.
     */
    public function enKey(string $defaultName): string
    {
        $name = $this->normalizeUnicode($defaultName);
        $name = $this->stripInvisible($name);
        $name = preg_replace(self::EN_TYPE_SUFFIX, '', $name) ?? $name;
        $name = preg_replace(self::EN_TYPE_PREFIX, '', $name) ?? $name;
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return trim($name);
    }

    private function normalizeUnicode(string $value): string
    {
        $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);

        return $normalized === false ? $value : $normalized;
    }

    /**
     * Invisible characters stand in for a space in the legacy data (e.g. "Quan Ba​​Commune") —
     * replace them WITH a space, then collapse, so word-boundary suffix rules keep matching.
     * (mb_str_split — a byte-level split would corrupt the multi-byte Vietnamese letters.)
     */
    private function stripInvisible(string $value): string
    {
        $spaced = str_replace(mb_str_split(self::INVISIBLE_CHARS), ' ', $value);
        $collapsed = preg_replace('/ {2,}/', ' ', $spaced) ?? $spaced;

        return trim($collapsed);
    }
}
