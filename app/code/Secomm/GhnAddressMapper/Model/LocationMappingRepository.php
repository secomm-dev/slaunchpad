<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Model;

use Secomm\GhnAddressMapper\Api\LocationMappingRepositoryInterface;
use Secomm\GhnAddressMapper\Api\Data\LocationMappingInterface;
use Secomm\GhnAddressMapper\Api\Data\LocationMappingSearchResultsInterface;
use Secomm\GhnAddressMapper\Api\Data\LocationMappingSearchResultsInterfaceFactory;
use Secomm\GhnAddressMapper\Model\Data\LocationMappingDataFactory;
use Secomm\GhnAddressMapper\Model\ResourceModel\LocationMapping as LocationMappingResource;
use Secomm\GhnAddressMapper\Model\ResourceModel\LocationMapping\CollectionFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class LocationMappingRepository implements LocationMappingRepositoryInterface
{
    public function __construct(
        protected LocationMappingResource $resource,
        protected LocationMappingDataFactory $dataFactory,
        protected CollectionFactory $collectionFactory,
        protected LocationMappingSearchResultsInterfaceFactory $searchResultsFactory,
        protected CollectionProcessorInterface $collectionProcessor,
        protected CacheInterface $cache
    ) {
    }

    public function getById(int $id): LocationMappingInterface
    {
        $mapping = $this->dataFactory->create();
        $this->resource->load($mapping, $id);
        if (!$mapping->getEntityId()) {
            throw new NoSuchEntityException(__('Mapping with id "%1" does not exist.', $id));
        }
        return $mapping;
    }

    public function getList(SearchCriteriaInterface $criteria): LocationMappingSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($criteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    public function save(LocationMappingInterface $mapping, bool $cleanCache = true): LocationMappingInterface
    {
        try {
            if (!$mapping->getCountryId()) {
                $mapping->setCountryId('VN');
            }
            if (!$mapping->getRegionName() && $mapping->getRegionId()) {
                $mapping->setRegionName($this->resource->getRegionNameById($mapping->getRegionId()));
            }
            if (!$mapping->getGhnProvinceName() && $mapping->getGhnProvinceId()) {
                $mapping->setGhnProvinceName($this->resource->getProvinceName($mapping->getGhnProvinceId()));
            }
            if (!$mapping->getGhnDistrictName() && $mapping->getGhnDistrictId()) {
                $mapping->setGhnDistrictName($this->resource->getDistrictName($mapping->getGhnDistrictId()));
            }
            if (!$mapping->getGhnWardName() && $mapping->getGhnWardCode()) {
                $mapping->setGhnWardName($this->resource->getWardName($mapping->getGhnWardCode()));
            }
            if ($mapping->getCityId() && !$mapping->getCityName()) {
                $mapping->setCityName($this->resource->getCityNameById($mapping->getCityId()));
            }

            $this->resource->save($mapping);
            if ($cleanCache) {
                $this->cleanCache();
            }
            return $mapping;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save mapping: %1', $e->getMessage()));
        }
    }

    public function delete(LocationMappingInterface $mapping): bool
    {
        try {
            $this->resource->delete($mapping);
            $this->cleanCache();
            return true;
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete mapping: %1', $e->getMessage()));
        }
    }

    public function deleteById(int $id): bool
    {
        return $this->delete($this->getById($id));
    }

    public function findByAddress(int $regionId, int $cityId): ?LocationMappingInterface
    {
        $row = $this->resource->findByAddress($regionId, $cityId);
        if (!$row) {
            return null;
        }
        $mapping = $this->dataFactory->create();
        $mapping->setData($row);
        return $mapping;
    }

    public function findByMapping(int $cityId, string $ghnWardCode): ?LocationMappingInterface
    {
        $row = $this->resource->findByMapping($cityId, $ghnWardCode);
        if (!$row) {
            return null;
        }
        $mapping = $this->dataFactory->create();
        $mapping->setData($row);
        return $mapping;
    }

    /**
     * Clean the mapping cache tag so LocationResolver serves fresh data.
     */
    private function cleanCache(): void
    {
        $this->cache->clean([Config::CACHE_TAG]);
    }
}
