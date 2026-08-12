<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Api;

use Secomm\GhnAddressMapper\Api\Data\LocationMappingInterface;
use Secomm\GhnAddressMapper\Api\Data\LocationMappingSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface LocationMappingRepositoryInterface
{
    /**
     * @param int $id
     * @return LocationMappingInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $id): LocationMappingInterface;

    /**
     * @param SearchCriteriaInterface $criteria
     * @return LocationMappingSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $criteria): LocationMappingSearchResultsInterface;

    /**
     * @param LocationMappingInterface $mapping
     * @param bool $cleanCache Whether to clean the mapping cache after save (default true)
     * @return LocationMappingInterface
     * @throws CouldNotSaveException
     */
    public function save(LocationMappingInterface $mapping, bool $cleanCache = true): LocationMappingInterface;

    /**
     * @param LocationMappingInterface $mapping
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(LocationMappingInterface $mapping): bool;

    /**
     * @param int $id
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $id): bool;

    /**
     * @param int $regionId
     * @param int $cityId
     * @return LocationMappingInterface|null
     */
    public function findByAddress(int $regionId, int $cityId): ?LocationMappingInterface;

    /**
     * Status-agnostic lookup by the natural dedup key (city_id + ghn_ward_code).
     * Used by the CSV importer to detect whether a specific mapping already exists.
     *
     * @param int $cityId
     * @param string $ghnWardCode
     * @return LocationMappingInterface|null
     */
    public function findByMapping(int $cityId, string $ghnWardCode): ?LocationMappingInterface;
}
