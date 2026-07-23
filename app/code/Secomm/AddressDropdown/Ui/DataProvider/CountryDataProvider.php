<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Ui\DataProvider;

use Magento\Directory\Model\ResourceModel\Country\Collection;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Locale\ListsInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Magento\Ui\DataProvider\SearchResultFactory;
use Secomm\AddressDropdown\Api\Data\CountryInterface;
use Secomm\AddressDropdown\Query\Country\GetListQuery;

/**
 * DataProvider component.
 */
class CountryDataProvider extends DataProvider
{
    /**
     * @var Collection
     */
    protected Collection $collection;
    /**
     * @var ListsInterface
     */
    protected $localeLists;
    /**
     * @var GetListQuery
     */
    private GetListQuery $getListQuery;
    /**
     * @var SearchResultFactory
     */
    private SearchResultFactory $searchResultFactory;


    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param GetListQuery $getListQuery
     * @param SearchResultFactory $searchResultFactory
     * @param CollectionFactory $collectionFactory
     * @param ListsInterface $localeLists
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        GetListQuery $getListQuery,
        SearchResultFactory $searchResultFactory,
        CollectionFactory $collectionFactory,
        ListsInterface $localeLists,
        array $meta = [],
        array $data = []
    )
    {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
        $this->getListQuery = $getListQuery;
        $this->collection = $collectionFactory->create();
        $this->localeLists = $localeLists;
        $this->searchResultFactory = $searchResultFactory;
    }

    /**
     * Returns searching result.
     *
     * @return SearchResultInterface
     */
    public function getSearchResult()
    {
        $searchCriteria = $this->getSearchCriteria();
        $result = $this->getListQuery->execute($searchCriteria);

        return $this->searchResultFactory->create(
            $result->getItems(),
            $result->getTotalCount(),
            $searchCriteria,
            CountryInterface::COUNTRY_ID
        );
    }
}
