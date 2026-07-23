<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Query\Region;

use Secomm\AddressDropdown\Model\ResourceModel\RegionModel\Collection;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Locale\ListsInterface;
use Secomm\AddressDropdown\Mapper\RegionDataMapper;

/**
 * Get CityName list by search criteria query.
 */
class GetListQuery
{
    /**
     * @var RequestInterface
     */
    protected $request;
    /**
     * @var CollectionProcessorInterface
     */
    private CollectionProcessorInterface $collectionProcessor;
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;
    /**
     * @var RegionDataMapper
     */
    private RegionDataMapper $entityDataMapper;
    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    /**
     * @var SearchResultsInterfaceFactory
     */
    private SearchResultsInterfaceFactory $searchResultFactory;
    /**
     * @var ListsInterface
     */
    private $localeLists;

    public function __construct(
        CollectionProcessorInterface  $collectionProcessor,
        CollectionFactory             $collectionFactory,
        RegionDataMapper              $entityDataMapper,
        SearchCriteriaBuilder         $searchCriteriaBuilder,
        SearchResultsInterfaceFactory $searchResultFactory,
        ListsInterface                $localeLists,
        RequestInterface              $request
    )
    {
        $this->collectionProcessor = $collectionProcessor;
        $this->collectionFactory = $collectionFactory;
        $this->entityDataMapper = $entityDataMapper;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->searchResultFactory = $searchResultFactory;
        $this->localeLists = $localeLists;
        $this->request = $request;
    }

    /**
     * Get CityName list by search criteria.
     *
     * @param SearchCriteriaInterface|null $searchCriteria
     * @param $countryId
     *
     * @return SearchResultsInterface
     */
    public function execute($countryId = null, ?SearchCriteriaInterface $searchCriteria = null): SearchResultsInterface
    {
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();

        if ($searchCriteria === null) {
            $searchCriteria = $this->searchCriteriaBuilder->create();
        } else {
            $this->collectionProcessor->process($searchCriteria, $collection);
        }

        // Add filter Country
        if ($countryId !== null && $countryId !== '') {
            $collection->addFieldToFilter('country_id', ['eq' => $countryId]);
        }

        $entityDataObjects = $this->entityDataMapper->map($collection);

        /** @var SearchResultsInterface $searchResult */
        $searchResult = $this->searchResultFactory->create();
        $searchResult->setItems($entityDataObjects);
        $searchResult->setTotalCount($collection->getSize());
        $searchResult->setSearchCriteria($searchCriteria);

        return $searchResult;
    }
}
