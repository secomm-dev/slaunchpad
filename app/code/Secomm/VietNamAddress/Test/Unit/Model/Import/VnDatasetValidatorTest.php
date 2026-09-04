<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model\Import;

use PHPUnit\Framework\TestCase;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Secomm\VietNamAddress\Model\Import\VnDatasetReader;
use Secomm\VietNamAddress\Model\Import\VnDatasetValidator;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * DEC-FEATYA2C0W-003 — dataset contract validation: counts, per-scheme code patterns,
 * uniqueness, hierarchy integrity, region coverage, collision suffix preservation
 * (19 groups / 38 rows on VN_ADMIN_PRE_2025), name hygiene (invisible chars rejected,
 * non-ASCII name_en ACCEPTED — ethnolinguistic names).
 */
class VnDatasetValidatorTest extends TestCase
{
    private VnDatasetValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new VnDatasetValidator();
    }

    public function testAcceptsContractCompliant2025Dataset(): void
    {
        $dataset = $this->dataset(VnSchemes::VN_ADMIN_2025);

        $this->assertSame([], $this->validator->validate(VnSchemes::VN_ADMIN_2025, $dataset['regions'], $dataset['units']));
    }

    public function testAcceptsContractCompliantPre2025Dataset(): void
    {
        $dataset = $this->dataset(VnSchemes::VN_ADMIN_PRE_2025);

        $this->assertSame([], $this->validator->validate(VnSchemes::VN_ADMIN_PRE_2025, $dataset['regions'], $dataset['units']));
    }

    /**
     * Regression (bootstrap fix): the SHIPPED canonical CSVs must pass the dataset
     * contract — region codes are "VN-XX" in the files, not bare 2-digit official codes
     * (the stale 2-digit pattern rejected all 34 region rows → "34 error(s)").
     */
    public function testShippedCanonicalDatasetsPassValidation(): void
    {
        $registrar = $this->createMock(ComponentRegistrarInterface::class);
        $registrar->method('getPath')->willReturn(dirname(__DIR__, 4));
        $reader = new VnDatasetReader($registrar);

        $dataset2025 = $reader->read(VnSchemes::VN_ADMIN_2025);
        $this->assertCount(34, $dataset2025['regions']);
        $this->assertCount(3321, $dataset2025['units']);
        $this->assertSame(
            [],
            $this->validator->validate(VnSchemes::VN_ADMIN_2025, $dataset2025['regions'], $dataset2025['units'])
        );

        $datasetPre = $reader->read(VnSchemes::VN_ADMIN_PRE_2025);
        $this->assertCount(63, $datasetPre['regions']);
        $this->assertCount(11294, $datasetPre['units']);
        $this->assertSame(
            [],
            $this->validator->validate(VnSchemes::VN_ADMIN_PRE_2025, $datasetPre['regions'], $datasetPre['units'])
        );
    }

    public function testAcceptsNonAsciiEnglishNames(): void
    {
        $dataset = $this->dataset(VnSchemes::VN_ADMIN_2025);
        $dataset['units'][0]['name_en'] = 'Kon Plông';

        $this->assertSame(
            [],
            $this->validator->validate(VnSchemes::VN_ADMIN_2025, $dataset['regions'], $dataset['units'])
        );
    }

    public function testRejectsCountDeviation(): void
    {
        $dataset = $this->dataset(VnSchemes::VN_ADMIN_2025);
        array_pop($dataset['units']); // 3320 depth-1 rows instead of 3321

        $errors = $this->validator->validate(VnSchemes::VN_ADMIN_2025, $dataset['regions'], $dataset['units']);

        $this->assertStringContainsString('Depth-1 rows: expected 3321, got 3320', implode('; ', $errors));
    }

    public function testRejectsForeignSchemeCodePattern(): void
    {
        $dataset = $this->dataset(VnSchemes::VN_ADMIN_2025);
        $dataset['units'][0]['code'] = 'VNAP25-0000000000'; // pre-2025 code in the 2025 dataset

        $errors = $this->validator->validate(VnSchemes::VN_ADMIN_2025, $dataset['regions'], $dataset['units']);

        $this->assertStringContainsString('does not match the VN_ADMIN_2025 pattern', implode('; ', $errors));
    }

    public function testRejectsDuplicateCode(): void
    {
        $dataset = $this->dataset(VnSchemes::VN_ADMIN_2025);
        $dataset['units'][1]['code'] = $dataset['units'][0]['code'];

        $errors = $this->validator->validate(VnSchemes::VN_ADMIN_2025, $dataset['regions'], $dataset['units']);

        $this->assertStringContainsString('duplicate code', implode('; ', $errors));
    }

    public function testRejects2025DepthTwoRows(): void
    {
        $dataset = $this->dataset(VnSchemes::VN_ADMIN_2025);
        $lastCode = $dataset['units'][0]['code'];
        $dataset['units'][] = [
            'line' => 5000, 'region_code' => 'VN-01', 'code' => 'VNA25-0000000ABC',
            'parent_code' => $lastCode, 'name_vi' => 'X', 'name_en' => 'X',
        ];
        // Keep the depth-1 count contract intact by removing one depth-1 row.
        $remove = $dataset['units'][1]['code'];
        $dataset['units'] = array_values(array_filter(
            $dataset['units'],
            fn (array $row): bool => $row['code'] !== $remove
        ));

        $errors = $this->validator->validate(VnSchemes::VN_ADMIN_2025, $dataset['regions'], $dataset['units']);

        $this->assertStringContainsString('must not contain depth-2 rows', implode('; ', $errors));
    }

    public function testRejectsCrossRegionParent(): void
    {
        $dataset = $this->dataset(VnSchemes::VN_ADMIN_PRE_2025);
        foreach ($dataset['units'] as $index => $row) {
            if ($row['code'] === 'VNAP25-0000002736') { // first non-collision ward (i=38)
                $dataset['units'][$index]['region_code'] = 'VN-02';
            }
        }

        $errors = $this->validator->validate(VnSchemes::VN_ADMIN_PRE_2025, $dataset['regions'], $dataset['units']);

        $this->assertStringContainsString('belongs to another region', implode('; ', $errors));
    }

    public function testRejectsUnresolvedParent(): void
    {
        $dataset = $this->dataset(VnSchemes::VN_ADMIN_PRE_2025);
        foreach ($dataset['units'] as $index => $row) {
            if ($row['code'] === 'VNAP25-0000002736') {
                $dataset['units'][$index]['parent_code'] = 'VNAP25-0000000ABC'; // no such depth-1 row
            }
        }

        $errors = $this->validator->validate(VnSchemes::VN_ADMIN_PRE_2025, $dataset['regions'], $dataset['units']);

        $this->assertStringContainsString('does not resolve to a depth-1 row', implode('; ', $errors));
    }

    public function testRejectsUndefinedRegionReference(): void
    {
        $dataset = $this->dataset(VnSchemes::VN_ADMIN_2025);
        $dataset['units'][0]['region_code'] = 'VN-99';

        $errors = $this->validator->validate(VnSchemes::VN_ADMIN_2025, $dataset['regions'], $dataset['units']);

        $this->assertStringContainsString('undefined region_code "VN-99"', implode('; ', $errors));
    }

    public function testRejectsNonCanonicalRegionCode(): void
    {
        $dataset = $this->dataset(VnSchemes::VN_ADMIN_2025);
        // Bare official code (legacy directory format) instead of the canonical "VN-XX".
        $dataset['regions'][0]['region_code'] = '01';
        $dataset['units'][0]['region_code'] = '01';

        $errors = $this->validator->validate(VnSchemes::VN_ADMIN_2025, $dataset['regions'], $dataset['units']);

        $this->assertStringContainsString('region_code "01" is not a canonical "VN-XX" region code', implode('; ', $errors));
    }

    public function testRejectsDuplicateRegionCode(): void
    {
        $dataset = $this->dataset(VnSchemes::VN_ADMIN_2025);
        $dataset['regions'][1]['region_code'] = $dataset['regions'][0]['region_code'];

        $errors = $this->validator->validate(VnSchemes::VN_ADMIN_2025, $dataset['regions'], $dataset['units']);

        $this->assertStringContainsString('duplicate region_code', implode('; ', $errors));
    }

    public function testRejectsInvisibleCharactersInNames(): void
    {
        $dataset = $this->dataset(VnSchemes::VN_ADMIN_2025);
        $dataset['units'][0]['name_en'] = "Quan Ba\u{200B}\u{200B}Commune";

        $errors = $this->validator->validate(VnSchemes::VN_ADMIN_2025, $dataset['regions'], $dataset['units']);

        $this->assertStringContainsString('invisible/zero-width characters', implode('; ', $errors));
    }

    public function testRejectsFiveColumnDatasetWithoutRegions(): void
    {
        $dataset = $this->dataset(VnSchemes::VN_ADMIN_2025);
        $errors = $this->validator->validate(VnSchemes::VN_ADMIN_2025, [], $dataset['units']);

        $this->assertStringContainsString('use the 7-column canonical format', implode('; ', $errors));
    }

    public function testRejectsCollisionCountDeviation(): void
    {
        $dataset = $this->dataset(VnSchemes::VN_ADMIN_PRE_2025);
        // Strip the suffix from one collision row -> 37 suffix rows (its group keeps 1 row,
        // so the group count stays 19 — the row count is what catches the drift).
        foreach ($dataset['units'] as $index => $row) {
            if ($row['code'] === 'VNAP25-0000002710') { // first collision row (i=0)
                $dataset['units'][$index]['name_vi'] = 'Yên Viên';
            }
        }

        $errors = $this->validator->validate(VnSchemes::VN_ADMIN_PRE_2025, $dataset['regions'], $dataset['units']);

        $this->assertStringContainsString('Collision rows: expected 38, got 37', implode('; ', $errors));
    }

    public function testRejectsSuffixIn2025Scheme(): void
    {
        $dataset = $this->dataset(VnSchemes::VN_ADMIN_2025);
        $dataset['units'][0]['name_vi'] = 'Yên Viên (Thị trấn)';

        $errors = $this->validator->validate(VnSchemes::VN_ADMIN_2025, $dataset['regions'], $dataset['units']);

        $this->assertStringContainsString('must not contain collision suffixes', implode('; ', $errors));
    }

    // ------------------------------------------------------------------ synthetic datasets

    /**
     * Contract-compliant dataset for a scheme.
     *
     * @return array{regions: array<int, array<string, string|int>>, units: array<int, array<string, string|int>>}
     */
    private function dataset(string $scheme): array
    {
        $isPre = $scheme === VnSchemes::VN_ADMIN_PRE_2025;
        $regionCount = $isPre ? 63 : 34;
        $depth1 = $isPre ? 699 : 3321;
        $depth2 = $isPre ? 10595 : 0;
        $prefix = $isPre ? 'VNAP25' : 'VNA25';
        // Unique 10-char uppercase-hex suffix per index (code contract [0-9A-F]{10}).
        $hex = static fn (int $i): string => strtoupper(str_pad(dechex($i), 10, '0', STR_PAD_LEFT));

        $regions = [];
        $units = [];
        $line = 1;
        for ($i = 1; $i <= $regionCount; $i++) {
            // Canonical dataset region identity: "VN-" + the 2-digit official code.
            $code = 'VN-' . sprintf('%02d', $isPre ? $this->preRegionCode($i) : $i);
            $regions[] = [
                'line' => ++$line, 'region_code' => $code,
                'name_vi' => sprintf('Tỉnh %s', $code), 'name_en' => sprintf('Province %s', $code),
            ];
        }
        $regionCodes = array_map(static fn (array $row): string => (string)$row['region_code'], $regions);

        for ($i = 0; $i < $depth1; $i++) {
            $units[] = [
                'line' => ++$line, 'region_code' => $regionCodes[$i % $regionCount],
                'code' => sprintf('%s-%s', $prefix, $hex($i)), 'parent_code' => '',
                'name_vi' => sprintf('Huyện %04d', $i), 'name_en' => sprintf('District %04d', $i),
            ];
        }
        $districtRegionByCode = [];
        foreach ($units as $row) {
            $districtRegionByCode[(string)$row['code']] = (string)$row['region_code'];
        }

        for ($i = 0; $i < $depth2; $i++) {
            $isCollision = $i < 38;
            // Both rows of a collision pair share the parent district (and thus its region).
            $parentCode = $isCollision
                ? sprintf('VNAP25-%s', $hex(intdiv($i, 2)))
                : sprintf('VNAP25-%s', $hex(($i - 38) % 699));
            $nameVi = sprintf('Xã thường %04d', $i);
            if ($isCollision) {
                $base = sprintf('Yên Viên %02d', intdiv($i, 2));
                $nameVi = $i % 2 === 0 ? sprintf('%s (Thị trấn)', $base) : sprintf('%s (Xã)', $base);
            }
            $units[] = [
                'line' => ++$line,
                'region_code' => $districtRegionByCode[$parentCode],
                'code' => sprintf('VNAP25-%s', $hex(10000 + $i)),
                'parent_code' => $parentCode,
                'name_vi' => $nameVi,
                'name_en' => sprintf('Ward %04d', $i),
            ];
        }

        return ['regions' => $regions, 'units' => $units];
    }

    /**
     * The gapped pre-2025 official region code set (63 codes within 01..96).
     */
    private function preRegionCode(int $i): int
    {
        $codes = [
            1, 2, 4, 6, 8, 10, 11, 12, 14, 15, 17, 19, 20, 22, 24, 25, 26, 27, 30, 31, 33, 34,
            35, 36, 37, 38, 40, 42, 44, 45, 46, 48, 49, 51, 52, 54, 56, 58, 60, 62, 64, 66, 67,
            68, 70, 72, 74, 75, 77, 79, 80, 82, 83, 84, 86, 87, 89, 91, 92, 93, 94, 95, 96,
        ];

        return $codes[$i - 1];
    }
}
