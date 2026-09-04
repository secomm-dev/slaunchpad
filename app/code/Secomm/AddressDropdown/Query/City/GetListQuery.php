<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Query\City;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Api\AddressProfileResolverInterface;
use Secomm\AddressDropdown\Api\AddressSchemaProviderInterface;
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Api\Data\SchemaLevelInterface;
use Secomm\AddressDropdown\Api\HierarchyAddressImportInterface;
use Secomm\AddressDropdown\Mapper\CityDataMapper;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityCollection;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityCollectionFactory;

/**
 * Get City list by search criteria query.
 */
class GetListQuery
{
    /**
     * @var CollectionProcessorInterface
     */
    private CollectionProcessorInterface $collectionProcessor;

    /**
     * @var CityCollectionFactory
     */
    private CityCollectionFactory $entityCollectionFactory;

    /**
     * @var CityDataMapper
     */
    private CityDataMapper $entityDataMapper;

    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @var SearchResultsInterfaceFactory
     */
    private SearchResultsInterfaceFactory $searchResultFactory;

    /**
     * TASK-9EX975 Slice B: profile resolution for the AC-B3 depth-coverage flag.
     */
    private AddressProfileResolverInterface $profileResolver;

    private AddressSchemaProviderInterface $schemaProvider;

    private LoggerInterface $logger;

    /**
     * @param CollectionProcessorInterface $collectionProcessor
     * @param CityCollectionFactory $entityCollectionFactory
     * @param CityDataMapper $entityDataMapper
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SearchResultsInterfaceFactory $searchResultFactory
     * @param AddressProfileResolverInterface $profileResolver
     * @param AddressSchemaProviderInterface $schemaProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        CollectionProcessorInterface  $collectionProcessor,
        CityCollectionFactory         $entityCollectionFactory,
        CityDataMapper                $entityDataMapper,
        SearchCriteriaBuilder         $searchCriteriaBuilder,
        SearchResultsInterfaceFactory $searchResultFactory,
        AddressProfileResolverInterface $profileResolver,
        AddressSchemaProviderInterface  $schemaProvider,
        LoggerInterface                 $logger
    )
    {
        $this->collectionProcessor = $collectionProcessor;
        $this->entityCollectionFactory = $entityCollectionFactory;
        $this->entityDataMapper = $entityDataMapper;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->searchResultFactory = $searchResultFactory;
        $this->profileResolver = $profileResolver;
        $this->schemaProvider = $schemaProvider;
        $this->logger = $logger;
    }

    /**
     * Get City list by search criteria.
     *
     * @param $region
     * @param SearchCriteriaInterface|null $searchCriteria
     *
     * @return SearchResultsInterface
     */
    public function execute($regionId = null, ?SearchCriteriaInterface $searchCriteria = null): SearchResultsInterface
    {
        /** @var CityCollection $collection */
        $collection = $this->entityCollectionFactory->create();

        if ($searchCriteria === null) {
            $searchCriteria = $this->searchCriteriaBuilder->create();
        } else {
            $this->collectionProcessor->process($searchCriteria, $collection);
        }

        // Add filter Country
        if ($regionId !== null && $regionId !== '') {
            $collection->addFieldToFilter('region_id', ['eq' => $regionId]);
        }

        $entityDataObjects = $this->entityDataMapper->map($collection);
        $this->decorateWithHierarchy($entityDataObjects);

        /** @var SearchResultsInterface $searchResult */
        $searchResult = $this->searchResultFactory->create();
        $searchResult->setItems($entityDataObjects);
        $searchResult->setTotalCount($collection->getSize());
        $searchResult->setSearchCriteria($searchCriteria);

        return $searchResult;
    }

    /**
     * TASK-9EX975 Slice B — grid columns `parent` (parent default_name) and `level`
     * (1-based depth below the region, "⚠" suffix when the node sits deeper than the
     * region's resolved profile schema renders — AC-B3). Computed server-side per
     * listing page; nothing extra is persisted. Walks are bounded by MAX_DEPTH.
     *
     * @param CityInterface[] $items
     * @return void
     */
    private function decorateWithHierarchy(array $items): void
    {
        if (!$items) {
            return;
        }

        $parentNames = $this->parentNames($items);
        $parentMap = [];       // cityId => parentId|null — lazily filled walk steps, shared across the page
        $depthMemo = [];       // cityId => 1-based depth|null (null = broken/cycle)
        $regionDepthMemo = []; // regionId => deepest city level its profile renders (0 = unknown/unmapped)

        foreach ($items as $item) {
            $parentId = $item->getParentCityId();
            $item->setData(CityInterface::PARENT_NAME, $parentId !== null ? ($parentNames[(int)$parentId] ?? '') : '');

            $depth = $this->resolveDepth($item, $parentMap, $depthMemo);
            $level = $depth === null ? '' : (string)$depth;

            if ($depth !== null && $item->getRegionId() !== null) {
                $regionId = (int)$item->getRegionId();
                if (!array_key_exists($regionId, $regionDepthMemo)) {
                    $regionDepthMemo[$regionId] = $this->maxProfileCityDepth($regionId);
                }
                if ($regionDepthMemo[$regionId] > 0 && $depth > $regionDepthMemo[$regionId]) {
                    $level .= ' ⚠';
                }
            }

            $item->setData(CityInterface::LEVEL, $level);
        }
    }

    /**
     * Parent default names for the page's rows — one bounded IN lookup (parents may live
     * outside the current page).
     *
     * @param CityInterface[] $items
     * @return array<int, string>
     */
    private function parentNames(array $items): array
    {
        $parentIds = [];
        foreach ($items as $item) {
            if ($item->getParentCityId() !== null) {
                $parentIds[(int)$item->getParentCityId()] = true;
            }
        }
        if (!$parentIds) {
            return [];
        }

        $names = [];
        foreach ($this->entityCollectionFactory->create()
                     ->addFieldToFilter(CityInterface::CITY_ID, ['in' => array_keys($parentIds)]) as $parent) {
            $names[(int)$parent->getCityId()] = (string)$parent->getDefaultName();
        }

        return $names;
    }

    /**
     * Depth via parent chain with memoisation: depth(node) = 1 + depth(parent), root = 1.
     * Broken chains / cycles yield null (row displayed undecorated).
     *
     * @param CityInterface $item
     * @param array<int, int|null> $parentMap shared, lazily filled
     * @param array<int, int|null> $depthMemo shared across the page
     * @return int|null
     */
    private function resolveDepth(CityInterface $item, array &$parentMap, array &$depthMemo): ?int
    {
        $cityId = $item->getCityId();
        if ($cityId !== null && array_key_exists((int)$cityId, $depthMemo)) {
            return $depthMemo[(int)$cityId];
        }
        if ($cityId !== null) {
            $parentMap[(int)$cityId] = $item->getParentCityId();
        }

        $depth = 1;
        $current = $item->getParentCityId();
        $visited = [];

        while ($current !== null) {
            $cur = (int)$current;
            if ($cityId !== null && $cur === (int)$cityId) {
                return null; // corrupt data: the row is its own ancestor
            }
            if (isset($visited[$cur])) {
                return null; // cycle above
            }
            if (array_key_exists($cur, $depthMemo) && $depthMemo[$cur] !== null) {
                $depth += $depthMemo[$cur];
                break;
            }
            if (count($visited) >= HierarchyAddressImportInterface::MAX_DEPTH) {
                return null;
            }
            $visited[$cur] = true;
            if (!array_key_exists($cur, $parentMap)) {
                $parentMap[$cur] = $this->parentOf($cur);
            }
            $depth++;
            $current = $parentMap[$cur];
        }

        if ($cityId !== null) {
            $depthMemo[(int)$cityId] = $depth;
        }

        return $depth;
    }

    /**
     * Fetch one city's parent id (walk step outside the page rows).
     */
    private function parentOf(int $cityId): ?int
    {
        $parent = $this->entityCollectionFactory->create()
            ->addFieldToFilter(CityInterface::CITY_ID, $cityId)
            ->getFirstItem();

        return $parent->isEmpty() || $parent->getParentCityId() === null ? null : (int)$parent->getParentCityId();
    }

    /**
     * Deepest city level the profile resolved for the region's country renders
     * (0 = unmapped country / resolution failure → no flag).
     */
    private function maxProfileCityDepth(int $regionId): int
    {
        try {
            $connection = $this->entityCollectionFactory->create()->getConnection();
            $countryId = $connection->fetchOne(
                $connection->select()
                    ->from('directory_country_region', ['country_id'])
                    ->where('region_id = ?', $regionId)
            );
            if ($countryId === false || $countryId === null) {
                return 0;
            }
            $profile = $this->profileResolver->resolve((string)$countryId);
            if ($profile === null) {
                return 0;
            }
            $max = 0;
            foreach ($this->schemaProvider->getSchema($profile->getCode()) as $level) {
                if ($level->getEntityType() === SchemaLevelInterface::ENTITY_TYPE_CITY
                    && $level->getDepth() > $max
                ) {
                    $max = $level->getDepth();
                }
            }

            return $max;
        } catch (\Throwable $e) {
            $this->logger->warning('City grid profile depth resolution skipped: ' . $e->getMessage());

            return 0;
        }
    }
}
