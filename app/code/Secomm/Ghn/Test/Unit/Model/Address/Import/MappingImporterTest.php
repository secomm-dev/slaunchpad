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
use Secomm\Ghn\Model\Address\Import\MappingImporter;
use Secomm\Ghn\Model\Cache\MappingCache;
use Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\Ghn\Model\ResourceModel\AddressMapping;
use Secomm\Ghn\Model\ResourceModel\AddressUnit;

/**
 * TASK-TBM30R / AC-L3/L4 — mapping import: APPROVED-only activation, full fail-loud validation
 * (duplicate / unknown canonical / unknown provider / conflict / dangling / malformed / scheme
 * mismatch), UPSERT refresh (never truncate), portable identity (no DB ids in files).
 */
class MappingImporterTest extends TestCase
{
    private VnAddressUnitProviderInterface&MockObject $unitProvider;

    private AddressUnit&MockObject $unitResource;

    private AddressMapping&MockObject $mappingResource;

    private MappingImporter $importer;

    private string $dir;

    protected function setUp(): void
    {
        $this->unitProvider = $this->createMock(VnAddressUnitProviderInterface::class);
        $this->unitResource = $this->createMock(AddressUnit::class);
        $this->mappingResource = $this->createMock(AddressMapping::class);
        $this->dir = sys_get_temp_dir() . '/ghn_mapimport_' . uniqid();
        mkdir($this->dir . '/mapping', 0775, true);

        $registrar = $this->createMock(ComponentRegistrar::class);
        $registrar->method('getPath')->willReturn('/nonexistent');

        $this->importer = new MappingImporter(
            new CsvReader(),
            new Manifest(new Json()),
            new DatasetPaths($registrar),
            $this->unitProvider,
            $this->unitResource,
            $this->mappingResource,
            $this->createMock(MappingCache::class)
        );

        // Canonical units VN-01 / VNA25-A1 exist; everything else dangles.
        $this->unitProvider->method('getUnit')->willReturnCallback(
            fn (string $scheme, string $code): ?VnAddressUnitInterface => in_array($code, ['VN-01', 'VNA25-A1'], true)
                ? new class ($code) implements VnAddressUnitInterface {
                    public function __construct(private readonly string $code)
                    {
                    }

                    public function getSchemeCode(): string
                    {
                        return 'VN_ADMIN_2025';
                    }

                    public function getCode(): string
                    {
                        return $this->code;
                    }

                    public function getParentCode(): ?string
                    {
                        return null;
                    }

                    public function getRegionCode(): string
                    {
                        return 'VN-01';
                    }

                    public function getLevel(): int
                    {
                        return 1;
                    }

                    public function getNameVi(): string
                    {
                        return 'X';
                    }

                    public function getNameEn(): string
                    {
                        return '';
                    }
                }
                : null
        );
        // GHN provider keys 1 and 11 exist in GHN_ADMIN_2025.
        $this->unitResource->method('fetchUnitByKey')->willReturnCallback(
            fn (string $scheme, string $key): ?array => $scheme === 'GHN_ADMIN_2025' && in_array($key, ['1', '11'], true)
                ? ['entity_id' => $key === '1' ? 501 : 502, 'provider_key' => $key, 'status' => 'ACTIVE']
                : null
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/mapping/VN_ADMIN_2025_TO_GHN_ADMIN_2025.csv');
        @rmdir($this->dir . '/mapping');
        @rmdir($this->dir);
    }

    private function writeMappingFile(string $content): void
    {
        file_put_contents($this->dir . '/mapping/VN_ADMIN_2025_TO_GHN_ADMIN_2025.csv', $content);
    }

    public function testApprovedRowsActivatedAndNonApprovedSkipped(): void
    {
        $this->writeMappingFile(<<<'CSV'
secomm_scheme_code,secomm_unit_code,ghn_scheme_code,ghn_provider_key,mapping_method,mapping_status,note
VN_ADMIN_2025,VN-01,GHN_ADMIN_2025,1,MANUAL,APPROVED,reviewed by TL
VN_ADMIN_2025,VNA25-A1,GHN_ADMIN_2025,11,EXACT_NAME,REVIEW_REQUIRED,suggested exact match
VN_ADMIN_2025,VNA25-B2,GHN_ADMIN_2025,,UNRESOLVED,UNRESOLVED,no candidate
VN_ADMIN_2025,VNA25-C3,GHN_ADMIN_2025,,MANUAL,AMBIGUOUS,candidates: 1 | 11
CSV);
        $captured = null;
        $this->mappingResource->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(function (array $rows) use (&$captured): int {
                $captured = $rows;

                return count($rows);
            });

        $report = $this->importer->import($this->dir, 'VN_ADMIN_2025', '2026-09-10 00:00:00', false);

        $scheme = $report['schemes']['VN_ADMIN_2025'];
        $this->assertSame(4, $scheme['total_rows']);
        $this->assertSame(1, $scheme['approved']);
        $this->assertSame(1, $scheme['skipped_review_required']);
        $this->assertSame(1, $scheme['skipped_unresolved']);
        $this->assertSame(1, $scheme['skipped_ambiguous']);

        // Local entity id resolved INSIDE the importer; file carried only the portable key.
        $this->assertSame(501, $captured[0]['ghn_address_unit_id']);
        $this->assertSame('APPROVED', $captured[0]['mapping_status']);
        $this->assertSame('MANUAL', $captured[0]['mapping_method']);
    }

    public function testDryRunWritesNothing(): void
    {
        $this->writeMappingFile(
            "secomm_scheme_code,secomm_unit_code,ghn_scheme_code,ghn_provider_key,mapping_method,mapping_status,note\n"
            . "VN_ADMIN_2025,VN-01,GHN_ADMIN_2025,1,MANUAL,APPROVED,x\n"
        );
        $this->mappingResource->expects($this->never())->method('upsert');

        $report = $this->importer->import($this->dir, 'VN_ADMIN_2025', null, true);
        $this->assertSame(1, $report['schemes']['VN_ADMIN_2025']['approved']);
    }

    public function testDuplicateCanonicalRowFailsLoud(): void
    {
        $this->writeMappingFile(
            "secomm_scheme_code,secomm_unit_code,ghn_scheme_code,ghn_provider_key,mapping_method,mapping_status,note\n"
            . "VN_ADMIN_2025,VN-01,GHN_ADMIN_2025,1,MANUAL,APPROVED,a\n"
            . "VN_ADMIN_2025,VN-01,GHN_ADMIN_2025,11,MANUAL,APPROVED,b\n"
        );
        $this->mappingResource->expects($this->never())->method('upsert');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('duplicate');
        $this->importer->import($this->dir, 'VN_ADMIN_2025', null, false);
    }

    public function testUnknownCanonicalUnitFailsLoud(): void
    {
        $this->writeMappingFile(
            "secomm_scheme_code,secomm_unit_code,ghn_scheme_code,ghn_provider_key,mapping_method,mapping_status,note\n"
            . "VN_ADMIN_2025,VNA25-GHOST,GHN_ADMIN_2025,1,MANUAL,APPROVED,x\n"
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('dangling');
        $this->importer->import($this->dir, 'VN_ADMIN_2025', null, false);
    }

    public function testUnknownGhnProviderFailsLoud(): void
    {
        $this->writeMappingFile(
            "secomm_scheme_code,secomm_unit_code,ghn_scheme_code,ghn_provider_key,mapping_method,mapping_status,note\n"
            . "VN_ADMIN_2025,VN-01,GHN_ADMIN_2025,999999,MANUAL,APPROVED,x\n"
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('dangling');
        $this->importer->import($this->dir, 'VN_ADMIN_2025', null, false);
    }

    public function testConflictingTargetFailsLoud(): void
    {
        // Two APPROVED canonical units pointing at the SAME GHN unit → conflict.
        $this->unitProvider->method('getUnit')->willReturn($this->createMock(VnAddressUnitInterface::class));
        $this->writeMappingFile(
            "secomm_scheme_code,secomm_unit_code,ghn_scheme_code,ghn_provider_key,mapping_method,mapping_status,note\n"
            . "VN_ADMIN_2025,VN-01,GHN_ADMIN_2025,1,MANUAL,APPROVED,a\n"
            . "VN_ADMIN_2025,VNA25-A1,GHN_ADMIN_2025,1,MANUAL,APPROVED,b\n"
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('conflicting');
        $this->importer->import($this->dir, 'VN_ADMIN_2025', null, false);
    }

    public function testUnknownStatusFailsLoud(): void
    {
        $this->writeMappingFile(
            "secomm_scheme_code,secomm_unit_code,ghn_scheme_code,ghn_provider_key,mapping_method,mapping_status,note\n"
            . "VN_ADMIN_2025,VN-01,GHN_ADMIN_2025,1,MANUAL,MAYBE,x\n"
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('mapping_status');
        $this->importer->import($this->dir, 'VN_ADMIN_2025', null, false);
    }

    public function testApprovedWithoutMethodFailsLoud(): void
    {
        $this->writeMappingFile(
            "secomm_scheme_code,secomm_unit_code,ghn_scheme_code,ghn_provider_key,mapping_method,mapping_status,note\n"
            . "VN_ADMIN_2025,VN-01,GHN_ADMIN_2025,1,,APPROVED,x\n"
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('mapping_method');
        $this->importer->import($this->dir, 'VN_ADMIN_2025', null, false);
    }

    public function testSchemeColumnMismatchFailsLoud(): void
    {
        $this->writeMappingFile(
            "secomm_scheme_code,secomm_unit_code,ghn_scheme_code,ghn_provider_key,mapping_method,mapping_status,note\n"
            . "VN_ADMIN_PRE_2025,VN-01,GHN_ADMIN_2025,1,MANUAL,APPROVED,x\n"
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('do not match');
        $this->importer->import($this->dir, 'VN_ADMIN_2025', null, false);
    }

    public function testMissingMappingFileFailsLoud(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('missing');
        $this->importer->import($this->dir, 'VN_ADMIN_2025', null, false);
    }

    public function testSameFileImportsAcrossDifferentEntityIdDatabases(): void
    {
        // AC-L4: the identical portable file resolves to DIFFERENT local ids on a "different DB".
        $this->writeMappingFile(
            "secomm_scheme_code,secomm_unit_code,ghn_scheme_code,ghn_provider_key,mapping_method,mapping_status,note\n"
            . "VN_ADMIN_2025,VN-01,GHN_ADMIN_2025,1,MANUAL,APPROVED,x\n"
        );

        $capturedPerDb = [];
        $this->mappingResource->method('upsert')->willReturnCallback(function (array $rows) use (&$capturedPerDb): int {
            $capturedPerDb[] = $rows;

            return count($rows);
        });

        $this->importer->import($this->dir, 'VN_ADMIN_2025', null, false);

        // "Second DB": the same provider key lives at a different local entity id there.
        $secondUnitResource = $this->createMock(AddressUnit::class);
        $secondUnitResource->method('fetchUnitByKey')->willReturnCallback(
            fn (string $scheme, string $key): ?array => $scheme === 'GHN_ADMIN_2025' && $key === '1'
                ? ['entity_id' => 99999, 'provider_key' => '1', 'status' => 'ACTIVE']
                : null
        );
        $secondImporter = new MappingImporter(
            new CsvReader(),
            new Manifest(new Json()),
            new DatasetPaths($this->createMock(ComponentRegistrar::class)),
            $this->unitProvider,
            $secondUnitResource,
            $this->mappingResource,
            $this->createMock(MappingCache::class)
        );
        $secondImporter->import($this->dir, 'VN_ADMIN_2025', null, false);

        // Same file, same portable identity (VN_ADMIN_2025 + VN-01 → ghn key 1), two local ids.
        $this->assertSame(501, $capturedPerDb[0][0]['ghn_address_unit_id']);
        $this->assertSame(99999, $capturedPerDb[1][0]['ghn_address_unit_id']);
        $this->assertSame($capturedPerDb[0][0]['secomm_unit_code'], $capturedPerDb[1][0]['secomm_unit_code']);
        $this->assertSame($capturedPerDb[0][0]['mapping_method'], $capturedPerDb[1][0]['mapping_method']);
    }
}
