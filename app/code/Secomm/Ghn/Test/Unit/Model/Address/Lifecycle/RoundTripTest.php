<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Address\Lifecycle;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Address\Dataset\DatasetPaths;
use Secomm\Ghn\Model\Address\Dataset\Manifest;
use Secomm\Ghn\Model\Address\Export\MasterDataExporter;
use Secomm\Ghn\Model\Address\Import\CsvReader;
use Secomm\Ghn\Model\Address\Import\MappingImporter;
use Secomm\Ghn\Model\Address\Import\MasterDataImporter;
use Secomm\Ghn\Model\Address\Mapping\GhnMappingResolver;
use Secomm\Ghn\Model\Address\Sync\MasterDataFetcher;
use Secomm\Ghn\Model\Address\Sync\UnitPersister;
use Secomm\Ghn\Model\Cache\MappingCache;
use Secomm\Ghn\Model\Config;
use Secomm\Ghn\Model\ResourceModel\AddressMapping;
use Secomm\Ghn\Model\ResourceModel\AddressUnit;
use Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;

/**
 * TASK-TBM30R / AC-L10 round trip: GHN provider data → export → import into a "clean DB" →
 * reviewed mapping import → runtime resolver. Uses an in-memory row store behind the resource
 * mocks — one continuous data lineage through the real exporter/importer/resolver code.
 */
class RoundTripTest extends TestCase
{
    public function testProviderDataToExportToImportToResolver(): void
    {
        $dir = sys_get_temp_dir() . '/ghn_roundtrip_' . uniqid();
        mkdir($dir . '/mapping', 0775, true);

        $registrar = $this->createMock(ComponentRegistrar::class);
        $paths = new DatasetPaths($registrar);

        // 1) GHN provider data (fetcher fixture, verified legacy field contract upstream).
        $fetcher = $this->createMock(MasterDataFetcher::class);
        $fetcher->method('supports')->willReturn('GHN_ADMIN_PRE_2025');
        $fetcher->method('fetch')->willReturn([
            ['provider_key' => '201', 'provider_id' => '201', 'provider_code' => null, 'parent_key' => null, 'depth' => 1, 'name' => 'TP. Hồ Chí Minh', 'extension_names' => null, 'status' => 'ACTIVE'],
            ['provider_key' => '1442', 'provider_id' => '1442', 'provider_code' => null, 'parent_key' => '201', 'depth' => 2, 'name' => 'Quận 1', 'extension_names' => null, 'status' => 'ACTIVE'],
            ['provider_key' => '90733', 'provider_id' => null, 'provider_code' => '90733', 'parent_key' => '1442', 'depth' => 3, 'name' => 'Phường Bến Nghé', 'extension_names' => null, 'status' => 'ACTIVE'],
        ]);

        // 2) Export → deterministic dataset dir.
        $exporter = new MasterDataExporter(
            ['GHN_ADMIN_PRE_2025' => $fetcher],
            $paths,
            new Manifest(new Json()),
            $this->createMock(Config::class)
        );
        $exporter->export('GHN_ADMIN_PRE_2025', $dir, '2026.09.10');

        // 3) Import into a "clean DB" (in-memory store).
        $db = $this->inMemoryUnitStore();
        $unitResource = $db['resource'];
        $masterImporter = new MasterDataImporter(
            new CsvReader(),
            new Manifest(new Json()),
            $paths,
            new UnitPersister($unitResource)
        );
        $masterImporter->import($dir, 'GHN_ADMIN_PRE_2025', null, false);

        // 4) Reviewed mapping file (offline review output) → import.
        $canonical = $this->canonicalProvider();
        $mappingStore = ['rows' => []];
        $mappingResource = $this->inMemoryMappingStore($mappingStore);
        file_put_contents(
            $paths->mappingFile($dir, 'VN_ADMIN_PRE_2025', 'GHN_ADMIN_PRE_2025'),
            "secomm_scheme_code,secomm_unit_code,ghn_scheme_code,ghn_provider_key,mapping_method,mapping_status,note\n"
            . "VN_ADMIN_PRE_2025,VN-01,GHN_ADMIN_PRE_2025,201,MANUAL,APPROVED,TL reviewed\n"
            . "VN_ADMIN_PRE_2025,VNAP25-D1,GHN_ADMIN_PRE_2025,1442,MANUAL,APPROVED,TL reviewed\n"
            . "VN_ADMIN_PRE_2025,VNAP25-W1,GHN_ADMIN_PRE_2025,90733,MANUAL,APPROVED,TL reviewed\n"
        );
        $mappingImporter = new MappingImporter(
            new CsvReader(),
            new Manifest(new Json()),
            $paths,
            $canonical,
            $unitResource,
            $mappingResource,
            $this->createMock(MappingCache::class)
        );
        $mappingImporter->import($dir, 'VN_ADMIN_PRE_2025', null, false);

        // 5) Runtime resolver over the imported APPROVED mapping only.
        $resolver = new GhnMappingResolver(
            $mappingResource,
            $unitResource,
            $this->createMock(MappingCache::class)
        );
        $location = $resolver->resolve('VN_ADMIN_PRE_2025', 'VNAP25-W1');

        $this->assertTrue($location->hasCompleteLegacyTriple());
        $this->assertSame('201', $location->getProvinceId());
        $this->assertSame('1442', $location->getDistrictId());
        $this->assertSame('90733', $location->getWardCode());
        $this->assertSame('Phường Bến Nghé', $location->getWardName());

        // Re-importing the same exported dataset is idempotent (0 disabled, stable identities).
        $second = $masterImporter->import($dir, 'GHN_ADMIN_PRE_2025', null, false);
        $this->assertSame(0, $second['schemes']['GHN_ADMIN_PRE_2025']['disabled']);

        @unlink($paths->masterFile($dir, 'GHN_ADMIN_PRE_2025'));
        @unlink($paths->mappingFile($dir, 'VN_ADMIN_PRE_2025', 'GHN_ADMIN_PRE_2025'));
        @unlink($paths->manifestFile($dir));
        @rmdir($dir . '/master');
        @rmdir($dir . '/mapping');
        @rmdir($dir);
    }

    /**
     * In-memory secomm_ghn_address_unit behind the AddressUnit mock.
     *
     * @return array{resource: AddressUnit&MockObject, store: array}
     */
    private function inMemoryUnitStore(): array
    {
        $store = ['rows' => [], 'nextId' => 1];
        $resource = $this->createMock(AddressUnit::class);

        $resource->method('fetchKeys')->willReturnCallback(function (string $scheme) use (&$store): array {
            $keys = [];
            foreach ($store['rows'] as $row) {
                if ($row['scheme_code'] === $scheme) {
                    $keys[$row['provider_key']] = $row['entity_id'];
                }
            }

            return $keys;
        });

        $resource->method('upsert')->willReturnCallback(function (array $rows) use (&$store): int {
            foreach ($rows as $row) {
                $index = $row['scheme_code'] . '|' . $row['provider_key'];
                if (!isset($store['rows'][$index])) {
                    $row['entity_id'] = $store['nextId']++;
                } else {
                    $row['entity_id'] = $store['rows'][$index]['entity_id'];
                }
                $store['rows'][$index] = $row;
            }

            return count($rows);
        });

        $resource->method('disableMissing')->willReturnCallback(function (string $scheme, array $keep, string $version) use (&$store): int {
            $disabled = 0;
            foreach ($store['rows'] as $index => $row) {
                if ($row['scheme_code'] === $scheme && !isset($keep[$row['provider_key']])) {
                    $store['rows'][$index]['status'] = 'DISABLED';
                    $disabled++;
                }
            }

            return $disabled;
        });

        $resource->method('fetchUnit')->willReturnCallback(function (int $entityId) use (&$store): ?array {
            foreach ($store['rows'] as $row) {
                if ($row['entity_id'] === $entityId) {
                    return $row;
                }
            }

            return null;
        });

        $resource->method('fetchUnitByKey')->willReturnCallback(function (string $scheme, string $key) use (&$store): ?array {
            return $store['rows'][$scheme . '|' . $key] ?? null;
        });

        return ['resource' => $resource, 'store' => &$store];
    }

    /**
     * In-memory secomm_ghn_address_mapping behind the AddressMapping mock.
     *
     * @return AddressMapping&MockObject
     */
    private function inMemoryMappingStore(array &$store): AddressMapping
    {
        $resource = $this->createMock(AddressMapping::class);

        $resource->method('upsert')->willReturnCallback(function (array $rows) use (&$store): int {
            foreach ($rows as $row) {
                $store['rows'][$row['secomm_scheme_code'] . '|' . $row['secomm_unit_code']] = $row;
            }

            return count($rows);
        });

        $resource->method('fetchByScheme')->willReturnCallback(function (string $scheme) use (&$store): array {
            $out = [];
            foreach ($store['rows'] as $row) {
                if ($row['secomm_scheme_code'] === $scheme) {
                    $out[$row['secomm_unit_code']] = $row;
                }
            }

            return $out;
        });

        $resource->method('findApproved')->willReturnCallback(function (string $scheme, string $code) use (&$store): ?array {
            $row = $store['rows'][$scheme . '|' . $code] ?? null;

            return ($row !== null && $row['mapping_status'] === 'APPROVED') ? $row : null;
        });

        return $resource;
    }

    /**
     * Canonical identity provider: VN-01 (region), VNAP25-D1 (district), VNAP25-W1 (ward) exist.
     */
    private function canonicalProvider(): VnAddressUnitProviderInterface
    {
        return new class () implements VnAddressUnitProviderInterface {
            private const UNITS = [
                'VN-01' => [1, 'VN-01', 'Thành Phố Hồ Chí Minh (canonical)'],
                'VNAP25-D1' => [2, 'VN-01', 'Quận 1 (canonical)'],
                'VNAP25-W1' => [3, 'VNAP25-D1', 'Phường Bến Nghé (canonical)'],
            ];

            public function getUnit(string $schemeCode, string $code): ?VnAddressUnitInterface
            {
                if (!isset(self::UNITS[$code])) {
                    return null;
                }

                return new class ($schemeCode, $code, self::UNITS[$code]) implements VnAddressUnitInterface {
                    public function __construct(
                        private readonly string $scheme,
                        private readonly string $code,
                        private readonly array $data
                    ) {
                    }

                    public function getSchemeCode(): string
                    {
                        return $this->scheme;
                    }

                    public function getCode(): string
                    {
                        return $this->code;
                    }

                    public function getParentCode(): ?string
                    {
                        return $this->data[1];
                    }

                    public function getRegionCode(): string
                    {
                        return 'VN-01';
                    }

                    public function getLevel(): int
                    {
                        return $this->data[0];
                    }

                    public function getNameVi(): string
                    {
                        return $this->data[2];
                    }

                    public function getNameEn(): string
                    {
                        return '';
                    }
                };
            }

            public function getChildren(string $schemeCode, string $parentCode): array
            {
                return [];
            }

            public function countByScheme(string $schemeCode): int
            {
                return count(self::UNITS);
            }
        };
    }
}
