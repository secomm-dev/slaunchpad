<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model\Import;

use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Exception\LocalizedException;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * DEC-FEATYA2C0W-003 — reads a versioned VN scheme dataset from the module's Files/
 * directory. Tolerates UTF-8 BOM and CRLF line endings; NFC-normalises display names.
 *
 * Canonical unit header (7 columns — region names embedded per row):
 *   region_code,region_name_vi,region_name_en,code,parent_code,name_vi,name_en
 * The legacy 5-column header (no region names) parses too, but yields no region rows —
 * the validator then fails with an explicit "use the 7-column canonical format" error.
 *
 * Region rows are DERIVED from the embedded per-row region names (one name variant per
 * region code is enforced while reading): the dataset is self-contained, no separate
 * region file and no DB-generated ids ever enter the source files.
 */
class VnDatasetReader
{
    public const HEADER_7 = ['region_code', 'region_name_vi', 'region_name_en', 'code', 'parent_code', 'name_vi', 'name_en'];
    public const HEADER_5 = ['region_code', 'code', 'parent_code', 'name_vi', 'name_en'];

    private const BOM = "\xEF\xBB\xBF";

    public function __construct(
        private readonly ComponentRegistrarInterface $componentRegistrar,
        /** @var array<string, string> scheme => file name override (alternate datasets/tests) */
        private readonly array $files = []
    ) {
    }

    /**
     * @return array{regions: array<int, array{line: int, region_code: string, name_vi: string, name_en: string}>, units: array<int, array{line: int, region_code: string, code: string, parent_code: string, name_vi: string, name_en: string}>, header: string}
     * @throws LocalizedException unknown scheme, missing file, wrong header/shape, inconsistent region names
     */
    public function read(string $scheme): array
    {
        $path = $this->resolvePath($scheme);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new LocalizedException(__('Unable to open dataset file: %1', $path));
        }

        try {
            return $this->parse($handle, $path);
        } finally {
            fclose($handle);
        }
    }

    public function getFileForScheme(string $scheme): string
    {
        $file = VnSchemes::unitFile($scheme);
        if ($this->files !== []) {
            VnSchemes::assertKnown($scheme);

            return $this->files[$scheme] ?? $file;
        }

        return $file;
    }

    private function resolvePath(string $scheme): string
    {
        VnSchemes::assertKnown($scheme);
        $moduleDir = $this->componentRegistrar->getPath('module', 'Secomm_VietNamAddress');
        $path = rtrim($moduleDir, '/') . '/Files/' . $this->getFileForScheme($scheme);
        if (!is_readable($path)) {
            throw new LocalizedException(
                __('Dataset file not readable: %1 (regenerate it per the DEC-FEATYA2C0W-003 canonical 7-column spec).', $path)
            );
        }

        return $path;
    }

    /**
     * @return array{regions: array, units: array, header: string}
     */
    private function parse($handle, string $path): array
    {
        $regions = [];
        $units = [];
        $regionNames = [];
        $line = 0;
        $header = null;

        while (($raw = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $line++;
            if ($line === 1) {
                $header = $this->assertHeader($raw, $path);
                continue;
            }
            if (count($raw) === 1 && trim((string)$raw[0]) === '') {
                continue; // trailing blank line
            }
            $expected = count($header);
            if (count($raw) !== $expected) {
                throw new LocalizedException(
                    __('%1 line %2: expected %3 columns, got %4.', $path, $line, $expected, count($raw))
                );
            }

            if ($expected === count(self::HEADER_7)) {
                [$regionCode, $regionNameVi, $regionNameEn, $code, $parentCode, $nameVi, $nameEn] = array_map(
                    fn (string $value): string => trim($value),
                    $raw
                );
                $this->rememberRegion($regionNames, $regionCode, $regionNameVi, $regionNameEn, $line, $path);
            } else {
                [$regionCode, $code, $parentCode, $nameVi, $nameEn] = array_map(
                    fn (string $value): string => trim($value),
                    $raw
                );
            }

            $units[] = [
                'line' => $line,
                'region_code' => $regionCode,
                'code' => $code,
                'parent_code' => $parentCode,
                'name_vi' => $this->normalizeUnicode($nameVi),
                'name_en' => $this->normalizeUnicode($nameEn),
            ];
        }

        if ($units === []) {
            throw new LocalizedException(__('Dataset file %1 contains no unit rows.', $path));
        }

        foreach ($regionNames as $regionCode => $names) {
            // PHP casts numeric-string array keys to int — keep region codes canonical strings.
            $regions[] = [
                'line' => $names['line'],
                'region_code' => (string)$regionCode,
                'name_vi' => $names['name_vi'],
                'name_en' => $names['name_en'],
            ];
        }

        return ['regions' => $regions, 'units' => $units, 'header' => implode(',', $header)];
    }

    /**
     * One name variant per region code, enforced at read time (drift = data error).
     *
     * @param array<string, array{name_vi: string, name_en: string, line: int}> $regionNames
     */
    private function rememberRegion(array &$regionNames, string $regionCode, string $nameVi, string $nameEn, int $line, string $path): void
    {
        $nameVi = $this->normalizeUnicode($nameVi);
        $nameEn = $this->normalizeUnicode($nameEn);

        if (isset($regionNames[$regionCode])) {
            $existing = $regionNames[$regionCode];
            if ($existing['name_vi'] !== $nameVi || $existing['name_en'] !== $nameEn) {
                throw new LocalizedException(
                    __(
                        '%1 line %2: region "%3" carries conflicting embedded names ("%4"/"%5" vs "%6"/"%7" at line %8).',
                        $path,
                        $line,
                        $regionCode,
                        $nameVi,
                        $nameEn,
                        $existing['name_vi'],
                        $existing['name_en'],
                        $existing['line']
                    )
                );
            }

            return;
        }

        $regionNames[$regionCode] = ['name_vi' => $nameVi, 'name_en' => $nameEn, 'line' => $line];
    }

    /**
     * @param array<int, string> $raw
     * @return array<int, string> the matched header columns
     */
    private function assertHeader(array $raw, string $path): array
    {
        if (isset($raw[0])) {
            $raw[0] = preg_replace('/^' . preg_quote(self::BOM, '/') . '/u', '', $raw[0]) ?? $raw[0];
        }
        if ($raw === self::HEADER_7) {
            return self::HEADER_7;
        }
        if ($raw === self::HEADER_5) {
            return self::HEADER_5;
        }

        throw new LocalizedException(
            __('%1: wrong header. Expected "%2" (or legacy "%3"), got "%4".', $path, implode(',', self::HEADER_7), implode(',', self::HEADER_5), implode(',', $raw))
        );
    }

    /**
     * Canonical Unicode form (NFC) for every display name — mixed NFC/NFD sources would
     * otherwise break exact key matching downstream (verified: the installed DB holds both).
     */
    private function normalizeUnicode(string $value): string
    {
        $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);

        return $normalized === false ? $value : $normalized;
    }
}
