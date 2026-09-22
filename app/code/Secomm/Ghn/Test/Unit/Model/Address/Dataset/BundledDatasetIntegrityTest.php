<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Address\Dataset;

use Magento\Framework\Component\ComponentRegistrar;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Address\Dataset\MappingCsv;
use Secomm\Ghn\Model\Address\Dataset\MasterCsv;
use Secomm\Ghn\Model\Address\Import\CsvReader;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-6TNKDH / SPEC §20 — dataset-integrity gate over the REAL bundled `data/` directory
 * (file-to-file, no DB). Active since the v1.0.0 reviewed dataset (TASK-6TNKDH phase 2):
 * manifest checksums + record counts, portable identity only, hierarchy reconstructable,
 * APPROVED rows resolvable on both sides, 1:1 provider targets, full canonical coverage.
 */
class BundledDatasetIntegrityTest extends TestCase
{
    /**
     * Canonical source file per Secomm scheme. PRE_2025 MUST read the end-of-2024 snapshot —
     * the mapping is keyed to VN_ADMIN_PRE_2025_SNAPSHOT_2024 codes (task §2; the reference
     * DB layer was migrated to the same snapshot by RefreshVnAdminPre2025Snapshot2024 /
     * TASK-GS78X2). VnSchemes::unitFile() still points at the pre-snapshot legacy file.
     */
    private const CANONICAL_SOURCE_FILES = [
        'VN_ADMIN_2025' => null, // resolved via VnSchemes::unitFile()
        'VN_ADMIN_PRE_2025' => 'VN_ADMIN_PRE_2025_SNAPSHOT_2024_import.csv',
    ];

    /** Authoritative v1.0.0 coverage pins: Secomm scheme => APPROVED mapping rows. */
    private const EXPECTED_COVERAGE = [
        'VN_ADMIN_2025' => 3355,
        'VN_ADMIN_PRE_2025' => 10794,
    ];

    private const CANONICAL_HEADER = [
        'region_code', 'region_name_vi', 'region_name_en', 'code', 'parent_code', 'name_vi', 'name_en',
    ];

    private ComponentRegistrar $registrar;

    private CsvReader $reader;

    private string $dataDir;

    /** @var array<string, array<int, array<string, string>>> master scheme => rows (lazy) */
    private array $masterRows = [];

    protected function setUp(): void
    {
        $this->registrar = new ComponentRegistrar();
        $this->reader = new CsvReader();
        $this->dataDir = $this->registrar->getPath(ComponentRegistrar::MODULE, 'Secomm_Ghn') . '/data';
    }

    public function testManifestCoversEveryDatasetFileWithMatchingChecksumAndCount(): void
    {
        $manifestFile = $this->dataDir . '/manifest.json';
        self::assertFileExists($manifestFile);
        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) file_get_contents($manifestFile), true);
        self::assertIsArray($manifest);
        self::assertArrayHasKey('dataset_version', $manifest);

        self::assertArrayHasKey('files', $manifest);
        foreach ($manifest['files'] as $relative => $entry) {
            $absolute = $this->dataDir . '/' . $relative;
            self::assertFileExists($absolute);
            self::assertSame(
                $entry['sha256'],
                hash_file('sha256', $absolute),
                "Bundled file {$relative} does not match its manifest checksum"
            );

            $rows = $this->reader->read($absolute, $this->headerFor($relative));
            self::assertSame(
                (int) $entry['record_count'],
                count($rows),
                "Bundled file {$relative} does not match its manifest record_count"
            );
        }
    }

    /**
     * Standing gate: only a real reviewed release may ship. The two historical placeholders
     * (v0.0.0-empty bootstrap stub, v1.0.0-master-only intermediate) are hard-rejected.
     */
    public function testPlaceholderDatasetMustNotShipAsFinal(): void
    {
        $manifest = json_decode((string) file_get_contents($this->dataDir . '/manifest.json'), true);
        $version = (string) ($manifest['dataset_version'] ?? '');

        self::assertNotSame('0.0.0-empty', $version, 'Placeholder dataset must be replaced before release');
        self::assertNotSame('1.0.0-master-only', $version, 'Master-only dataset must be replaced by the reviewed mapping release');
        self::assertCount(4, $manifest['files'] ?? [], 'A release dataset must declare all four master/mapping files');

        foreach (array_keys($manifest['files']) as $relative) {
            self::assertFileExists($this->dataDir . '/' . $relative);
        }
    }

    public function testBundledMasterFilesAreHierarchicalPortableData(): void
    {
        // Both approved GHN schemes must ship a master file (placeholder counts as shipped-but-empty).
        $this->assertSame(
            ['GHN_ADMIN_2025', 'GHN_ADMIN_PRE_2025'],
            array_keys($this->masterRowsByScheme())
        );

        foreach ($this->masterRowsByScheme() as $scheme => $rows) {
            $keys = [];
            foreach ($rows as $row) {
                self::assertSame($scheme, $row['scheme_code'], 'scheme_code column must match the file scheme');

                $key = $row['provider_key'];
                self::assertNotSame('', $key);
                self::assertArrayNotHasKey($key, $keys, "duplicate provider_key {$key} in {$scheme}");
                $keys[$key] = true;

                self::assertContains($row['status'], ['ACTIVE', 'DISABLED']);
                self::assertArrayHasKey($row['level'], array_flip(['1', '2', '3']));

                if ($row['level'] === '1') {
                    self::assertSame('', $row['parent_provider_key'], 'province rows must not declare a parent');
                } else {
                    self::assertArrayHasKey(
                        $row['parent_provider_key'],
                        $keys,
                        "unit {$key} references missing parent {$row['parent_provider_key']} (file must be parent-complete)"
                    );
                }
            }
        }
    }

    public function testApprovedMappingsArePortableResolvableAndOneToOne(): void
    {
        foreach ([['VN_ADMIN_2025', 'GHN_ADMIN_2025'], ['VN_ADMIN_PRE_2025', 'GHN_ADMIN_PRE_2025']] as [$secomm, $ghn]) {
            $file = sprintf('%s/mapping/%s_TO_%s.csv', $this->dataDir, $secomm, $ghn);
            if (!is_file($file)) {
                continue; // mapping file optional in a partially-authored dataset (manifest governs)
            }

            $rows = $this->reader->read($file, MappingCsv::HEADER);
            $masterKeys = array_column($this->masterRowsByScheme()[$ghn] ?? [], 'provider_key');
            $canonicalCodes = $this->canonicalUnitCodes($secomm);
            // Canonical source must be readable for the cross-source resolvability check.
            self::assertNotEmpty($canonicalCodes);

            $seenCanonical = [];
            $seenTargets = [];
            $approvedCount = 0;
            foreach ($rows as $row) {
                self::assertSame($secomm, $row['secomm_scheme_code']);
                self::assertSame($ghn, $row['ghn_scheme_code']);
                self::assertContains($row['mapping_status'], MappingCsv::FILE_STATUSES);

                self::assertArrayNotHasKey($row['secomm_unit_code'], $seenCanonical, 'duplicate canonical mapping row');
                $seenCanonical[$row['secomm_unit_code']] = true;

                if ($row['mapping_status'] !== MappingCsv::STATUS_APPROVED) {
                    continue;
                }
                $approvedCount++;

                self::assertContains($row['mapping_method'], MappingCsv::METHODS);
                self::assertContains($row['ghn_provider_key'], $masterKeys, 'APPROVED row references provider key absent from bundled master');
                self::assertArrayHasKey($row['secomm_unit_code'], $canonicalCodes, 'APPROVED row references unknown canonical unit_code (dangling)');

                self::assertArrayNotHasKey(
                    $row['ghn_provider_key'],
                    $seenTargets,
                    "1:1 policy violated: provider {$row['ghn_provider_key']} targeted by two APPROVED canonical units"
                );
                $seenTargets[$row['ghn_provider_key']] = $row['secomm_unit_code'];
            }

            // Full canonical coverage: every supported canonical Secomm unit carries exactly one
            // APPROVED mapping (audit gate: unmapped = 0). Coverage is measured from canonical
            // Secomm units to GHN provider identity — provider-only master rows may stay unmapped.
            self::assertSame(count($canonicalCodes), $approvedCount, "APPROVED rows must cover every canonical {$secomm} unit");
            self::assertSame(self::EXPECTED_COVERAGE[$secomm], $approvedCount, "Authoritative {$secomm} coverage pin violated");
        }
    }

    /**
     * Master rows per scheme, parsed lazily from the bundled files.
     *
     * @return array<string, array<int, array<string, string>>>
     */
    private function masterRowsByScheme(): array
    {
        if ($this->masterRows !== []) {
            return $this->masterRows;
        }

        foreach (array_unique(array_values(DatasetPathsPairs::PAIRS)) as $ghnScheme) {
            $file = sprintf('%s/master/%s.csv', $this->dataDir, $ghnScheme);
            if (!is_file($file)) {
                continue;
            }
            $this->masterRows[$ghnScheme] = $this->reader->read($file, MasterCsv::HEADER);
        }

        return $this->masterRows;
    }

    /**
     * Canonical unit codes straight from the Secomm_VietNamAddress shipped dataset files,
     * mirroring CanonicalCsvProvider: the files carry no explicit region rows, so province
     * units are synthesized from the distinct region_code column — canonicalSecomm coverage
     * is measured against codes ∪ regions, exactly like the runtime provider's unit set.
     *
     * @return array<string, true>
     */
    private function canonicalUnitCodes(string $secommScheme): array
    {
        static $cache = [];
        if (isset($cache[$secommScheme])) {
            return $cache[$secommScheme];
        }

        $fileName = self::CANONICAL_SOURCE_FILES[$secommScheme] ?? VnSchemes::unitFile($secommScheme);
        $file = $this->registrar->getPath(ComponentRegistrar::MODULE, 'Secomm_VietNamAddress') . '/Files/' . $fileName;
        $rows = $this->reader->read($file, self::CANONICAL_HEADER);

        $codes = array_fill_keys(array_column($rows, 'code'), true);
        $codes += array_fill_keys(array_column($rows, 'region_code'), true);

        return $cache[$secommScheme] = $codes;
    }

    /**
     * Manifest-relative path → CSV header contract for record-count validation.
     *
     * @return list<string>
     */
    private function headerFor(string $relative): array
    {
        return str_starts_with($relative, 'master/') ? MasterCsv::HEADER : MappingCsv::HEADER;
    }
}

/**
 * Local pair table (mirrors DatasetPaths::SCHEME_PAIRS without instantiating Magento DI here).
 */
final class DatasetPathsPairs
{
    public const PAIRS = [
        'VN_ADMIN_2025' => 'GHN_ADMIN_2025',
        'VN_ADMIN_PRE_2025' => 'GHN_ADMIN_PRE_2025',
    ];
}
