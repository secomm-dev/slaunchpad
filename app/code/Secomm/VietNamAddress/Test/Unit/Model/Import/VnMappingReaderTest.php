<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model\Import;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Model\Import\VnMappingReader;

/**
 * TASK-J9AVGK — mapping CSV reader: header contract, BOM/CRLF tolerance, shape errors.
 */
class VnMappingReaderTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/_files/Mapping';

    protected function setUp(): void
    {
        if (!is_dir(self::FIXTURE_DIR)) {
            mkdir(self::FIXTURE_DIR, 0777, true);
        }
        if (!is_file(self::FIXTURE_DIR . '/ok.csv')) {
            file_put_contents(
                self::FIXTURE_DIR . '/ok.csv',
                "\xEF\xBB\xBFsource_scheme,source_code,target_scheme,target_code,relation_type\r\n" .
                "VN_ADMIN_PRE_2025,VNAP25-A,VN_ADMIN_2025,VNA25-C,MERGED_INTO\r\n" .
                "VN_ADMIN_PRE_2025,VNAP25-B,VN_ADMIN_2025,VNA25-C,MERGED_INTO\r\n"
            );
        }
        if (!is_file(self::FIXTURE_DIR . '/bad_header.csv')) {
            file_put_contents(self::FIXTURE_DIR . '/bad_header.csv', "\xEF\xBB\xBFsource,target,relation\r\n");
        }
        if (!is_file(self::FIXTURE_DIR . '/bad_columns.csv')) {
            file_put_contents(
                self::FIXTURE_DIR . '/bad_columns.csv',
                "source_scheme,source_code,target_scheme,target_code,relation_type\r\nVN_ADMIN_PRE_2025,VNAP25-A,VN_ADMIN_2025,MERGED_INTO\r\n"
            );
        }
    }

    public function testReadsRowsWithBomAndCrlf(): void
    {
        $rows = (new VnMappingReader())->read(self::FIXTURE_DIR . '/ok.csv');

        $this->assertCount(2, $rows);
        $this->assertSame('VN_ADMIN_PRE_2025', $rows[0]['source_scheme']);
        $this->assertSame('VNAP25-A', $rows[0]['source_code']);
        $this->assertSame('VNA25-C', $rows[0]['target_code']);
        $this->assertSame('MERGED_INTO', $rows[0]['relation_type']);
    }

    public function testRejectsWrongHeader(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('wrong header');
        (new VnMappingReader())->read(self::FIXTURE_DIR . '/bad_header.csv');
    }

    public function testRejectsWrongColumnCount(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('expected 5 columns');
        (new VnMappingReader())->read(self::FIXTURE_DIR . '/bad_columns.csv');
    }

    public function testRejectsUnreadableFile(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('not readable');
        (new VnMappingReader())->read('/nowhere/mapping.csv');
    }
}
