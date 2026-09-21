<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Address\Export;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Address\Dataset\DatasetPaths;
use Secomm\Ghn\Model\Address\Dataset\Manifest;
use Secomm\Ghn\Model\Address\Export\MasterDataExporter;
use Secomm\Ghn\Model\Address\Sync\MasterDataFetcher;
use Secomm\Ghn\Model\Config;

/**
 * TASK-TBM30R / AC-L1 — deterministic export: fixed schema/order, verbatim names, extension_names
 * JSON preserved, no entity_id, hierarchy reconstructable, manifest (counts + sha256), byte-identical
 * across runs.
 */
class MasterDataExporterTest extends TestCase
{
    private MasterDataFetcher&MockObject $fetcher;

    private MasterDataExporter $exporter;

    private string $dir;

    protected function setUp(): void
    {
        $this->fetcher = $this->createMock(MasterDataFetcher::class);
        $this->fetcher->method('supports')->willReturn('GHN_ADMIN_2025');
        $this->fetcher->method('fetch')->willReturn([
            ['provider_key' => '90733', 'provider_id' => null, 'provider_code' => '90733', 'parent_key' => '1442', 'depth' => 3, 'name' => 'Phường Bến Nghé (Q.1)', 'extension_names' => json_encode(['Ben Nghe'], JSON_UNESCAPED_UNICODE), 'status' => 'ACTIVE'],
            ['provider_key' => '1442', 'provider_id' => '1442', 'provider_code' => null, 'parent_key' => '201', 'depth' => 2, 'name' => 'Quận 1', 'extension_names' => null, 'status' => 'ACTIVE'],
            ['provider_key' => '201', 'provider_id' => '201', 'provider_code' => null, 'parent_key' => null, 'depth' => 1, 'name' => 'Thành Phố Hồ Chí Minh', 'extension_names' => null, 'status' => 'ACTIVE'],
        ]);

        $registrar = $this->createMock(\Magento\Framework\Component\ComponentRegistrar::class);
        $this->dir = sys_get_temp_dir() . '/ghn_export_' . uniqid();
        mkdir($this->dir, 0775, true);
        $registrar->method('getPath')->willReturn($this->dir); // dataset root irrelevant for export target

        $this->exporter = new MasterDataExporter(
            ['GHN_ADMIN_2025' => $this->fetcher],
            new DatasetPaths($registrar),
            new Manifest(new Json()),
            $this->createMock(Config::class)
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/master/GHN_ADMIN_2025.csv');
        @unlink($this->dir . '/manifest.json');
        @rmdir($this->dir . '/master');
        @rmdir($this->dir);
    }

    public function testExportIsDeterministicAndOrdered(): void
    {
        $first = $this->exporter->export('GHN_ADMIN_2025', $this->dir, '2026.09.10');
        $content1 = (string) file_get_contents($this->dir . '/master/GHN_ADMIN_2025.csv');
        $this->assertSame('2026.09.10', $first['dataset_version']);

        $this->exporter->export('GHN_ADMIN_2025', $this->dir, '2026.09.10');
        $content2 = (string) file_get_contents($this->dir . '/master/GHN_ADMIN_2025.csv');

        $this->assertSame($content1, $content2);

        $lines = explode("\n", trim($content1));
        $this->assertSame(
            'scheme_code,level,provider_key,provider_id,provider_code,parent_provider_key,name,extension_names,status',
            $lines[0]
        );
        // Depth-ASC then provider_key byte order — hierarchy reconstructable, no entity_id column.
        // PHP fputcsv quotes any field containing a space; enclosure inside JSON is doubled.
        $this->assertSame('GHN_ADMIN_2025,1,201,201,,,"Thành Phố Hồ Chí Minh",,ACTIVE', $lines[1]);
        $this->assertSame('GHN_ADMIN_2025,2,1442,1442,,201,"Quận 1",,ACTIVE', $lines[2]);
        $this->assertSame('GHN_ADMIN_2025,3,90733,,90733,1442,"Phường Bến Nghé (Q.1)","[""Ben Nghe""]",ACTIVE', $lines[3]);
    }

    public function testManifestCarriesCountsAndChecksum(): void
    {
        $this->exporter->export('GHN_ADMIN_2025', $this->dir, '2026.09.10');

        $manifest = json_decode((string) file_get_contents($this->dir . '/manifest.json'), true);
        $this->assertSame(3, $manifest['files']['master/GHN_ADMIN_2025.csv']['record_count']);
        $this->assertSame(
            hash_file('sha256', $this->dir . '/master/GHN_ADMIN_2025.csv'),
            $manifest['files']['master/GHN_ADMIN_2025.csv']['sha256']
        );
    }

    public function testUnknownSchemeFailsLoud(): void
    {
        $this->expectException(LocalizedException::class);
        $this->exporter->export('VN_ADMIN_2025', $this->dir);
    }
}
