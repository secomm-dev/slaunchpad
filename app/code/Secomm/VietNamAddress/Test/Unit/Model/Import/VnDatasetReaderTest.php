<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model\Import;

use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Model\Import\VnDatasetReader;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * DEC-FEATYA2C0W-003 — dataset reader contract: 7-column canonical header with embedded
 * region names, region derivation (one variant per code), BOM/CRLF tolerance, 5-column
 * legacy header acceptance, error paths.
 */
class VnDatasetReaderTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/_files';

    private ComponentRegistrarInterface&MockObject $registrar;

    protected function setUp(): void
    {
        $this->registrar = $this->createMock(ComponentRegistrarInterface::class);
        $this->registrar->method('getPath')->willReturn(self::FIXTURE_DIR);
    }

    private function reader(array $files): VnDatasetReader
    {
        return new VnDatasetReader($this->registrar, $files);
    }

    public function testReadsSevenColumnDatasetAndDerivesRegions(): void
    {
        $dataset = $this->reader([VnSchemes::VN_ADMIN_2025 => 'fixture_units_7col.csv'])
            ->read(VnSchemes::VN_ADMIN_2025);

        $this->assertCount(4, $dataset['units']);
        $this->assertSame('region_code,region_name_vi,region_name_en,code,parent_code,name_vi,name_en', $dataset['header']);

        // Two distinct regions derived from the embedded names, first-seen order preserved.
        $this->assertCount(2, $dataset['regions']);
        $this->assertSame(['region_code' => '32', 'name_vi' => 'An Giang', 'name_en' => 'An Giang'], $this->withoutLine($dataset['regions'][0]));
        $this->assertSame(['region_code' => '89', 'name_vi' => 'An Giang', 'name_en' => 'An Giang'], $this->withoutLine($dataset['regions'][1]));

        $this->assertSame('VNA25-3D6A6CF4D0', $dataset['units'][0]['code']);
        $this->assertSame('VNAP25-515AFEF59D', $dataset['units'][3]['code']);
        $this->assertSame('VNAP25-B70EDA95D6', $dataset['units'][3]['parent_code']);
        $this->assertSame('An Biên', $dataset['units'][0]['name_vi']);
    }

    public function testAcceptsFiveColumnHeaderWithoutRegions(): void
    {
        $dataset = $this->reader([VnSchemes::VN_ADMIN_2025 => 'fixture_units_5col.csv'])
            ->read(VnSchemes::VN_ADMIN_2025);

        $this->assertCount(1, $dataset['units']);
        $this->assertSame([], $dataset['regions']);
        $this->assertSame('region_code,code,parent_code,name_vi,name_en', $dataset['header']);
    }

    public function testRejectsConflictingEmbeddedRegionNames(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('conflicting embedded names');
        $this->reader([VnSchemes::VN_ADMIN_2025 => 'fixture_region_conflict.csv'])
            ->read(VnSchemes::VN_ADMIN_2025);
    }

    public function testRejectsWrongHeader(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('wrong header');
        $this->reader([VnSchemes::VN_ADMIN_2025 => 'fixture_bad_header.csv'])
            ->read(VnSchemes::VN_ADMIN_2025);
    }

    public function testRejectsWrongColumnCount(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('expected 7 columns');
        $this->reader([VnSchemes::VN_ADMIN_2025 => 'fixture_bad_columns.csv'])
            ->read(VnSchemes::VN_ADMIN_2025);
    }

    public function testRejectsUnknownScheme(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown Vietnam administrative scheme');
        $this->reader([])->read('vn_current');
    }

    public function testRejectsMissingFile(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('not readable');
        $this->reader([VnSchemes::VN_ADMIN_2025 => 'definitely_missing.csv'])
            ->read(VnSchemes::VN_ADMIN_2025);
    }

    /**
     * @param array<string, string|int> $row
     * @return array<string, string|int>
     */
    private function withoutLine(array $row): array
    {
        unset($row['line']);

        return $row;
    }
}
