<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Model\Import;

use Secomm\GhnAddressMapper\Api\Data\LocationMappingInterfaceFactory;
use Secomm\GhnAddressMapper\Api\LocationMappingRepositoryInterface;
use Secomm\GhnAddressMapper\Model\Config;
use Magento\Framework\App\CacheInterface;
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
        protected LoggerInterface $logger
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
     * Process a single CSV row and import/update/skip the mapping.
     *
     * @param array $row Raw CSV row
     * @param array<string, int> $headerMap Column name => index
     * @param int $lineNum Line number (for error messages)
     * @param bool $updateExisting Whether to update existing mappings
     * @return array{action: string|null, error: string|null}
     */
    public function processRow(array $row, array $headerMap, int $lineNum, bool $updateExisting): array
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

        if (!$regionId || !$cityId || !$ghnProvinceId || !$ghnDistrictId || !$ghnWardCode) {
            return ['action' => null, 'error' => __('[Line %1] Invalid data: missing required fields.', $lineNum)];
        }

        try {
            $existingMapping = $this->repository->findByAddress($regionId, $cityId);

            if ($existingMapping && $updateExisting) {
                $existingMapping->setCountryId($countryId);
                $existingMapping->setGhnProvinceId($ghnProvinceId);
                $existingMapping->setGhnDistrictId($ghnDistrictId);
                $existingMapping->setGhnWardCode($ghnWardCode);
                $existingMapping->setRegionName(null);
                $existingMapping->setGhnProvinceName(null);
                $existingMapping->setGhnDistrictName(null);
                $existingMapping->setGhnWardName(null);
                $this->repository->save($existingMapping);
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
                $this->repository->save($mapping);
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

        $lineNum = 2;
        foreach ($csvData as $row) {
            $rowResult = $this->processRow($row, $headerMap, $lineNum, $updateExisting);

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

        $this->cache->clean([Config::CACHE_TAG]);

        return $result;
    }
}
