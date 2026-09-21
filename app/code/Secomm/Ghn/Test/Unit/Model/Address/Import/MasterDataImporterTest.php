<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Address\Import;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Address\Dataset\DatasetPaths;
use Secomm\Ghn\Model\Address\Dataset\Manifest;
use Secomm\Ghn\Model\Address\Import\CsvReader;
use Secomm\Ghn\Model\Address\Import\MasterDataImporter;
use Secomm\Ghn\Model\Address\Sync\UnitPersister;
use Secomm\Ghn\Model\ResourceModel\AddressUnit;

/**
 * TASK-TBM30R / AC-L2/L5 — master import from CSV: portable rows reach the persister, scheme
 * column enforced, manifest checksum honored, bundled placeholder dataset bootstraps deterministically.
 */
class MasterDataImporterTest extends TestCase
{
    private AddressUnit&MockObject $unitResource;

    private MasterDataImporter $importer;

    private string $dir;

    protected function setUp(): void
    {
        $this->unitResource = $this->createMock(AddressUnit::class);
        $this->dir = sys_get_temp_dir() . '/ghn_mimport_' . uniqid();
        mkdir($this->dir . '/master', 0775, true);

        $registrar = $this->createMock(ComponentRegistrar::class);
        $registrar->method('getPath')->willReturn('/nonexistent-module-dir');

        $this->importer = new MasterDataImporter(
            new CsvReader(),
            new Manifest(new Json()),
            new DatasetPaths($registrar),
            new UnitPersister($this->unitResource)
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/master/GHN_ADMIN_2025.csv');
        @unlink($this->dir . '/manifest.json');
        @rmdir($this->dir . '/master');
        @rmdir($this->dir);
    }

    private function writeMasterDataset(bool $withManifest = false, ?string $manifestSha = null): void
    {
        $content = <<<'CSV'
scheme_code,level,provider_key,provider_id,provider_code,parent_provider_key,name,extension_names,status
GHN_ADMIN_2025,1,201,201,,,Tp. Hồ Chí Minh,,ACTIVE
GHN_ADMIN_2025,2,11,11,,201,Phường Bến Nghé,,ACTIVE
CSV;
        file_put_contents($this->dir . '/master/GHN_ADMIN_2025.csv', $content);
        if ($withManifest) {
            file_put_contents($this->dir . '/manifest.json', json_encode([
                'dataset_version' => '2026.09.10',
                'files' => [
                    'master/GHN_ADMIN_2025.csv' => [
                        'scheme_code' => 'GHN_ADMIN_2025',
                        'record_count' => 2,
                        'sha256' => $manifestSha ?? hash_file('sha256', $this->dir . '/master/GHN_ADMIN_2025.csv'),
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    public function testImportPersistsPortableRowsWithManifestCheck(): void
    {
        $this->writeMasterDataset(withManifest: true);
        // Depth-1 rows insert first, so the depth-2 ward resolves parent "201" afterwards.
        // fetchKeys: initial state (1) + key-map refresh after each depth write (2..3).
        $keysMatcher = $this->exactly(3);
        $this->unitResource->expects($keysMatcher)
            ->method('fetchKeys')
            ->willReturnCallback(fn (): array => $keysMatcher->numberOfInvocations() === 1 ? [] : ['201' => 10]);

        $report = $this->importer->import($this->dir, 'GHN_ADMIN_2025', null, false);

        $this->assertSame('2026.09.10', $report['manifest_version']);
        $schemeReport = $report['schemes']['GHN_ADMIN_2025'];
        $this->assertSame(2, $schemeReport['records']);
        $this->assertSame('2026.09.10', $schemeReport['source_version']);
    }

    public function testChecksumMismatchRejectsImport(): void
    {
        $this->writeMasterDataset(withManifest: true, manifestSha: str_repeat('0', 64));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('checksum');
        $this->importer->import($this->dir, 'GHN_ADMIN_2025', null, false);
    }

    public function testWrongSchemeColumnRejectsImport(): void
    {
        $this->writeMasterDataset();
        file_put_contents(
            $this->dir . '/master/GHN_ADMIN_2025.csv',
            "scheme_code,level,provider_key,provider_id,provider_code,parent_provider_key,name,extension_names,status\nGHN_ADMIN_PRE_2025,1,x,x,,,Wrong,,ACTIVE\n"
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('scheme_code');
        $this->importer->import($this->dir, 'GHN_ADMIN_2025', null, false);
    }

    public function testSmallFixtureDatasetBootstrapsDeterministically(): void
    {
        // Deterministic bootstrap over a tiny fixture dataset (the REAL bundled data/ is covered
        // by the BundledDatasetIntegrityTest + the CLI e2e evidence).
        $fixture = $this->dir . '_fixture';
        mkdir($fixture . '/master', 0775, true);
        file_put_contents($fixture . '/master/GHN_ADMIN_2025.csv', <<<'CSV'
scheme_code,level,provider_key,provider_id,provider_code,parent_provider_key,name,extension_names,status
GHN_ADMIN_2025,1,1000000,1000000,,,Hà Nội,,ACTIVE
CSV);
        file_put_contents($fixture . '/manifest.json', json_encode([
            'dataset_version' => '1.0.0-fixture',
            'files' => [
                'master/GHN_ADMIN_2025.csv' => [
                    'scheme_code' => 'GHN_ADMIN_2025',
                    'record_count' => 1,
                    'sha256' => hash_file('sha256', $fixture . '/master/GHN_ADMIN_2025.csv'),
                ],
            ],
        ], JSON_UNESCAPED_UNICODE));

        $registrar = $this->createMock(ComponentRegistrar::class);
        $registrar->method('getPath')->willReturn('/nonexistent-module-dir');
        $importer = new MasterDataImporter(
            new CsvReader(),
            new Manifest(new Json()),
            new DatasetPaths($registrar),
            new UnitPersister($this->createMock(AddressUnit::class))
        );

        $report = $importer->import($fixture, 'GHN_ADMIN_2025', null, false);

        $this->assertSame('1.0.0-fixture', $report['manifest_version']);
        $this->assertSame(1, $report['schemes']['GHN_ADMIN_2025']['records']);
        $this->assertSame('1.0.0-fixture', $report['schemes']['GHN_ADMIN_2025']['source_version']);

        @unlink($fixture . '/master/GHN_ADMIN_2025.csv');
        @unlink($fixture . '/manifest.json');
        @rmdir($fixture . '/master');
        @rmdir($fixture);
    }
}
