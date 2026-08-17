<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\GhtkAddressMapImport;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\GhtkAddressMapImport\CsvReader;

class CsvReaderTest extends TestCase
{
    private CsvReader $reader;
    private array $paths = [];

    protected function setUp(): void
    {
        $this->reader = new CsvReader();
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }
        $this->paths = [];
    }

    private function writeCsv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ghtk_csv') . '.csv';
        file_put_contents($path, $content);
        $this->paths[] = $path;

        return $path;
    }

    public function testReadsValidRows(): void
    {
        $path = $this->writeCsv(
            "country_id,region_id,ward_id,ghtk_province,ghtk_district,ghtk_ward,is_active\n"
            . "VN,1157,1,Hà Nội,Hoàn Kiếm,Phường A,1\n"
            . "VN,1157,2,Hà Nội,,Phường B,1\n"
        );

        $rows = $this->reader->read($path);

        $this->assertCount(2, $rows);
        $this->assertSame('VN', $rows[0]['country_id']);
        $this->assertSame('1157', $rows[0]['region_id']);
        $this->assertSame('Hoàn Kiếm', $rows[0]['ghtk_district']);
        $this->assertSame('', $rows[1]['ghtk_district']);
    }

    public function testStripsUtf8Bom(): void
    {
        $bom = "\xEF\xBB\xBF";
        $path = $this->writeCsv(
            $bom . "country_id,region_id,ward_id,ghtk_province,ghtk_district,ghtk_ward,is_active\n"
            . "VN,1,1,p,,w,1\n"
        );

        $rows = $this->reader->read($path);

        $this->assertCount(1, $rows);
        $this->assertSame('VN', $rows[0]['country_id']);
    }

    public function testThrowsOnWrongHeader(): void
    {
        $path = $this->writeCsv("bad,header\nVN,1\n");
        $this->expectException(LocalizedException::class);
        $this->reader->read($path);
    }

    public function testThrowsOnEmptyFile(): void
    {
        $path = $this->writeCsv('');
        $this->expectException(LocalizedException::class);
        $this->reader->read($path);
    }

    public function testThrowsOnMissingFile(): void
    {
        $this->expectException(LocalizedException::class);
        $this->reader->read('/no/such/path/ghtk.csv');
    }

    public function testNormalisesColumnCountOverflowAndUnderflow(): void
    {
        // Extra column (overflow) + short row (underflow) — both normalised to header width.
        $path = $this->writeCsv(
            "country_id,region_id,ward_id,ghtk_province,ghtk_district,ghtk_ward,is_active\n"
            . "VN,1,1,p,d,w,1,EXTRA_COLUMN\n"   // 8 cols
            . "VN,1,2,p,,w\n"                    // 6 cols
        );

        $rows = $this->reader->read($path);

        $this->assertCount(2, $rows);
        $this->assertSame('VN', $rows[0]['country_id']);
        $this->assertSame('VN', $rows[1]['country_id']);
    }
}
