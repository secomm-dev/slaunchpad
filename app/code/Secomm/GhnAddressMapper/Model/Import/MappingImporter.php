<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Model\Import;

use Secomm\GhnAddressMapper\Api\Data\LocationMappingInterfaceFactory;
use Secomm\GhnAddressMapper\Api\LocationMappingRepositoryInterface;
use Secomm\GhnAddressMapper\Model\Config;
use Secomm\GhnAddressMapper\Model\ResourceModel\LocationMapping as LocationMappingResource;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Shared CSV import service for Location Mappings.
 * Used by both CLI (ImportMappingCommand) and Admin (Import controller).
 */
class MappingImporter
{
    /**
     * @var string[] Required CSV column names (lowercase)
     */
    private const REQUIRED_COLUMNS = ['region_id', 'city_id', 'ghn_province_id', 'ghn_district_id', 'ghn_ward_code'];

    public function __construct(
        protected LocationMappingInterfaceFactory $mappingFactory,
        protected LocationMappingRepositoryInterface $repository,
        protected CacheInterface $cache,
        protected LoggerInterface $logger,
        protected LocationMappingResource $locationMappingResource,
        protected ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Parse and validate CSV headers.
     *
     * @param array $rawHeaders Raw header row from CSV
     * @return array<string, int> Column name (lowercase) => index
     * @throws \Magento\Framework\Exception\LocalizedException If required columns are missing
     */
    public function parseHeaders(array $rawHeaders): array
    {
        $headers = array_map(function ($h) {
            return strtolower(trim((string)$h));
        }, $rawHeaders);
        $headerMap = array_flip($headers);

        foreach (self::REQUIRED_COLUMNS as $col) {
            if (!isset($headerMap[$col])) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Missing required CSV column: %1', $col)
                );
            }
        }

        return $headerMap;
    }

    /**
     * Collect distinct ID/code values from all rows for bulk name resolution.
     *
     * @param array $csvData CSV data rows (without header)
     * @param array<string, int> $headerMap Column name => index
     * @return array{region_ids: int[], city_ids: int[], province_ids: int[], district_ids: int[], ward_codes: string[]}
     */
    private function collectDistinctIds(array $csvData, array $headerMap): array
    {
        $regionIds = [];
        $cityIds = [];
        $provinceIds = [];
        $districtIds = [];
        $wardCodes = [];

        foreach ($csvData as $row) {
            $data = [];
            foreach ($headerMap as $colName => $idx) {
                $data[$colName] = trim((string)($row[$idx] ?? ''));
            }

            $regionId = (int)($data['region_id'] ?? 0);
            $cityId = (int)($data['city_id'] ?? 0);
            $provinceId = (int)($data['ghn_province_id'] ?? 0);
            $districtId = (int)($data['ghn_district_id'] ?? 0);
            $wardCode = $data['ghn_ward_code'] ?? '';

            if ($regionId > 0) {
                $regionIds[$regionId] = true;
            }
            if ($cityId > 0) {
                $cityIds[$cityId] = true;
            }
            if ($provinceId > 0) {
                $provinceIds[$provinceId] = true;
            }
            if ($districtId > 0) {
                $districtIds[$districtId] = true;
            }
            if ($wardCode !== '') {
                $wardCodes[$wardCode] = true;
            }
        }

        return [
            'region_ids' => array_keys($regionIds),
            'city_ids' => array_keys($cityIds),
            'province_ids' => array_keys($provinceIds),
            'district_ids' => array_keys($districtIds),
            'ward_codes' => array_keys($wardCodes),
        ];
    }

    /**
     * Build name lookup maps from distinct IDs using bulk queries.
     *
     * @param array{region_ids: int[], city_ids: int[], province_ids: int[], district_ids: int[], ward_codes: string[]} $distinctIds
     * @return array{region: array<int, string|null>, city: array<int, string|null>, province: array<int, string|null>, district: array<int, string|null>, ward: array<string, string|null>}
     */
    private function buildNameMaps(array $distinctIds): array
    {
        return [
            'region' => $this->locationMappingResource->getRegionNameMap($distinctIds['region_ids']),
            'city' => $this->locationMappingResource->getCityNameMap($distinctIds['city_ids']),
            'province' => $this->locationMappingResource->getProvinceNameMap($distinctIds['province_ids']),
            'district' => $this->locationMappingResource->getDistrictNameMap($distinctIds['district_ids']),
            'ward' => $this->locationMappingResource->getWardNameMap($distinctIds['ward_codes']),
        ];
    }

    /**
     * Process a single CSV row and import/update/skip the mapping.
     *
     * Dedup key: (city_id, ghn_ward_code) — allows 1:N mapping per city_id.
     *
     * @param array $row Raw CSV row
     * @param array<string, int> $headerMap Column name => index
     * @param int $lineNum Line number (for error messages)
     * @param bool $updateExisting Whether to update existing mappings
     * @param array{region: array<int, string|null>, city: array<int, string|null>, province: array<int, string|null>, district: array<int, string|null>, ward: array<string, string|null>} $nameMaps Pre-resolved name lookup maps
     * @return array{action: string|null, error: string|null}
     */
    public function processRow(array $row, array $headerMap, int $lineNum, bool $updateExisting, array $nameMaps = []): array
    {
        $data = [];
        foreach ($headerMap as $colName => $idx) {
            $data[$colName] = trim((string)($row[$idx] ?? ''));
        }

        $countryId = $data['country_id'] ?? 'VN';
        $regionId = (int)($data['region_id'] ?? 0);
        $cityId = (int)($data['city_id'] ?? 0);
        $ghnProvinceId = (int)($data['ghn_province_id'] ?? 0);
        $ghnDistrictId = (int)($data['ghn_district_id'] ?? 0);
        $ghnWardCode = $data['ghn_ward_code'] ?? '';
        $priority = (int)($data['priority'] ?? 0);

        if (!$regionId || !$cityId || !$ghnProvinceId || !$ghnDistrictId || !$ghnWardCode) {
            return ['action' => null, 'error' => __('[Line %1] Invalid data: missing required fields.', $lineNum)];
        }

        try {
            $existingMapping = $this->repository->findByMapping($cityId, $ghnWardCode);

            if ($existingMapping && $existingMapping->getRegionId() !== $regionId) {
                $this->logger->warning('GHN Address Mapper: CSV region_id differs from persisted row', [
                    'city_id' => $cityId,
                    'csv_region_id' => $regionId,
                    'persisted_region_id' => $existingMapping->getRegionId(),
                ]);
            }

            // Resolve names from pre-built maps to skip per-row lookups in save()
            $regionName = $nameMaps['region'][$regionId] ?? null;
            $cityName = $nameMaps['city'][$cityId] ?? null;
            $provinceName = $nameMaps['province'][$ghnProvinceId] ?? null;
            $districtName = $nameMaps['district'][$ghnDistrictId] ?? null;
            $wardName = $nameMaps['ward'][$ghnWardCode] ?? null;

            if ($existingMapping && $updateExisting) {
                $existingMapping->setCountryId($countryId);
                $existingMapping->setGhnProvinceId($ghnProvinceId);
                $existingMapping->setGhnDistrictId($ghnDistrictId);
                $existingMapping->setGhnWardCode($ghnWardCode);
                $existingMapping->setPriority($priority);
                $existingMapping->setRegionName($regionName);
                $existingMapping->setCityName($cityName);
                $existingMapping->setGhnProvinceName($provinceName);
                $existingMapping->setGhnDistrictName($districtName);
                $existingMapping->setGhnWardName($wardName);
                $this->repository->save($existingMapping, false);
                return ['action' => 'updated', 'error' => null];
            } elseif ($existingMapping) {
                return ['action' => 'skipped', 'error' => null];
            } else {
                $mapping = $this->mappingFactory->create();
                $mapping->setCountryId($countryId);
                $mapping->setRegionId($regionId);
                $mapping->setCityId($cityId);
                $mapping->setGhnProvinceId($ghnProvinceId);
                $mapping->setGhnDistrictId($ghnDistrictId);
                $mapping->setGhnWardCode($ghnWardCode);
                $mapping->setStatus(1);
                $mapping->setPriority($priority);
                // Pre-set names so save() skips its per-row lookups
                $mapping->setRegionName($regionName);
                $mapping->setCityName($cityName);
                $mapping->setGhnProvinceName($provinceName);
                $mapping->setGhnDistrictName($districtName);
                $mapping->setGhnWardName($wardName);
                $this->repository->save($mapping, false);
                return ['action' => 'imported', 'error' => null];
            }
        } catch (\Exception $e) {
            $this->logger->error('GHN Address Mapper: Import error at line ' . $lineNum, ['exception' => $e]);
            return ['action' => null, 'error' => __('[Line %1] %2', $lineNum, $e->getMessage())];
        }
    }

    /**
     * Import all rows from parsed CSV data.
     *
     * Wraps the entire batch in a DB transaction for atomic visibility:
     * successful rows are committed together, failed rows are counted but
     * do not abort the transaction (best-effort semantics). Cache is cleaned
     * only after a successful commit.
     *
     * @param array $csvData Full CSV data including header row as first element
     * @param bool $updateExisting Whether to update existing mappings
     * @return array{imported: int, updated: int, skipped: int, failed: int, errors: string[]}
     */
    public function importAll(array $csvData, bool $updateExisting): array
    {
        $rawHeaders = array_shift($csvData);
        $headerMap = $this->parseHeaders($rawHeaders);

        $result = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => []
        ];

        // Bulk name resolution: 5 SELECTs for the whole batch instead of ≤6 per row
        $distinctIds = $this->collectDistinctIds($csvData, $headerMap);
        $nameMaps = $this->buildNameMaps($distinctIds);

        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();

        try {
            $lineNum = 2;
            foreach ($csvData as $row) {
                $rowResult = $this->processRow($row, $headerMap, $lineNum, $updateExisting, $nameMaps);

                if ($rowResult['error']) {
                    $result['failed']++;
                    $result['errors'][] = $rowResult['error'];
                } else {
                    switch ($rowResult['action']) {
                        case 'imported':
                            $result['imported']++;
                            break;
                        case 'updated':
                            $result['updated']++;
                            break;
                        case 'skipped':
                            $result['skipped']++;
                            break;
                    }
                }

                $lineNum++;
            }

            $connection->commit();
            // Clean cache only after successful commit — avoid wiping good data if nothing changed
            $this->cache->clean([Config::CACHE_TAG]);
        } catch (\Throwable $e) {
            $connection->rollBack();
            $this->logger->error('GHN Address Mapper: Import transaction failed', ['exception' => $e]);
            $result['errors'][] = __('Import transaction failed: %1', $e->getMessage());
            // Re-throw unexpected errors (deadlocks, connection loss) so callers know the batch failed
            if (!($e instanceof \Magento\Framework\Exception\LocalizedException)) {
                $result['failed'] += ($result['imported'] + $result['updated']);
                $result['imported'] = 0;
                $result['updated'] = 0;
            }
        }

        return $result;
    }
}
