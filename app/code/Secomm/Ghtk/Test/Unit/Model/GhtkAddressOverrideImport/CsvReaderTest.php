<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\GhtkAddressOverrideImport;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\GhtkAddressOverrideImport\CsvReader;

/**
 * TASK-7AJ3K8 r1 — override CSV reader: canonical-key header, BOM handling, width normalisation.
 */
class CsvReaderTest extends TestCase
{
    private const HEADER = 'scheme_code,province_code,ward_code,ghtk_province,ghtk_district,ghtk_ward,is_active,note';

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

    public function testReadsValidOverrideRows(): void
    {
        $path = $this->writeCsv(
            self::HEADER . "\n"
            . "VN_ADMIN_2025,VN-01,VNA25-AAAA,,,GHTK Ward A,1,evidence #1\n"
            . "VN_ADMIN_2025,VN-79,VNA25-BBBB,TP HCM GHTK,,,1,evidence #2\n"
        );

        $rows = $this->reader->read($path);

        $this->assertCount(2, $rows);
        $this->assertSame('VN_ADMIN_2025', $rows[0]['scheme_code']);
        $this->assertSame('VN-01', $rows[0]['province_code']);
        $this->assertSame('GHTK Ward A', $rows[0]['ghtk_ward']);
        $this->assertSame('TP HCM GHTK', $rows[1]['ghtk_province']);
        $this->assertSame('', $rows[1]['ghtk_ward']);
    }

    public function testStripsUtf8Bom(): void
    {
        $bom = "\xEF\xBB\xBF";
        $path = $this->writeCsv(
            $bom . self::HEADER . "\n"
            . "VN_ADMIN_2025,VN-01,VNA25-AAAA,,,W,1,\n"
        );

        $rows = $this->reader->read($path);

        $this->assertCount(1, $rows);
        $this->assertSame('VN_ADMIN_2025', $rows[0]['scheme_code']);
    }

    public function testThrowsOnWrongHeader(): void
    {
        $path = $this->writeCsv("bad,header\nx,y\n");
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
            self::HEADER . "\n"
            . "VN_ADMIN_2025,VN-01,VNA25-AAAA,,,W,1,note,EXTRA_COLUMN\n"  // 9 cols
            . "VN_ADMIN_2025,VN-01,VNA25-BBBB\n"                          // 3 cols
        );

        $rows = $this->reader->read($path);

        $this->assertCount(2, $rows);
        $this->assertSame('VN_ADMIN_2025', $rows[0]['scheme_code']);
        $this->assertSame('VNA25-AAAA', $rows[0]['ward_code']);
        $this->assertSame('VNA25-BBBB', $rows[1]['ward_code']);
        $this->assertSame('', $rows[1]['ghtk_ward']);
    }
}
