<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Model;

use Secomm\GhnAddressMapper\Api\LocationResolverInterface;
use Secomm\GhnAddressMapper\Api\Data\LocationResultInterface;
use Secomm\GhnAddressMapper\Model\Data\LocationResultFactory;
use Secomm\GhnAddressMapper\Model\ResourceModel\LocationMapping as LocationMappingResource;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class LocationResolver implements LocationResolverInterface
{
    public function __construct(
        protected LocationMappingResource $locationMappingResource,
        protected LocationResultFactory $locationResultFactory,
        protected CacheInterface $cache,
        protected LoggerInterface $logger
    ) {
    }

    public function resolve(int $regionId, int $cityId): LocationResultInterface
    {
        if (!$regionId || !$cityId) {
            $this->logger->warning(
                __('Incomplete address data for mapping: region_id=%1, city_id=%2',
                    $regionId, $cityId)
            );
            throw new NoSuchEntityException(
                __('Incomplete address data. Region and City are required.')
            );
        }

        $cacheKey = $this->buildCacheKey($regionId, $cityId);
        $cached = $this->cache->load($cacheKey);
        if ($cached !== false) {
            $data = json_decode($cached, true);
            if (is_array($data)) {
                return $this->createResult($data);
            }
        }

        $row = $this->locationMappingResource->findByAddress($regionId, $cityId);
        if (!$row) {
            $this->logger->error(
                __('No mapping found for address: region_id=%1, city_id=%2',
                    $regionId, $cityId)
            );
            throw new NoSuchEntityException(
                __('No GHN address mapping found for region_id=%1, city_id=%2',
                    $regionId, $cityId)
            );
        }

        $result = $this->createResult($row);
        $this->cache->save(
            json_encode($row),
            $cacheKey,
            [Config::CACHE_TAG],
            Config::CACHE_LIFETIME
        );

        return $result;
    }

    public function resolveByName(int $regionId, string $cityName): LocationResultInterface
    {
        if (!$regionId || $cityName === '') {
            $this->logger->warning(
                __('Incomplete address data for mapping: region_id=%1, city_name=%2',
                    $regionId, $cityName)
            );
            throw new NoSuchEntityException(
                __('Incomplete address data. Region and City are required.')
            );
        }

        $cityIdKey = 'ghn_addr_cityid_' . $regionId . '_' . md5($cityName);
        $cityId = false;
        $cached = $this->cache->load($cityIdKey);
        if ($cached !== false) {
            $cityId = (int)$cached;
        }
        if (!$cityId) {
            $cityId = $this->locationMappingResource->getCityIdByName($regionId, $cityName);
            if (!$cityId) {
                $this->logger->error(
                    __('Could not resolve city_id for region_id=%1, city_name=%2',
                        $regionId, $cityName)
                );
                throw new NoSuchEntityException(
                    __('No GHN address mapping found for region_id=%1, city_name=%2',
                        $regionId, $cityName)
                );
            }
            $this->cache->save((string)$cityId, $cityIdKey, [Config::CACHE_TAG], Config::CACHE_LIFETIME);
        }

        return $this->resolve($regionId, $cityId);
    }

    private function buildCacheKey(int $regionId, int $cityId): string
    {
        return 'ghn_addr_map_' . $regionId . '_' . $cityId;
    }

    private function createResult(array $data): LocationResultInterface
    {
        return $this->locationResultFactory->create([
            'data' => [
                LocationResultInterface::PROVINCE_ID => (int)$data['ghn_province_id'],
                LocationResultInterface::DISTRICT_ID => (int)$data['ghn_district_id'],
                LocationResultInterface::WARD_CODE => (string)$data['ghn_ward_code'],
            ]
        ]);
    }
}
