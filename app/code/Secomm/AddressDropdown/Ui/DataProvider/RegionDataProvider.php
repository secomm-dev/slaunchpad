<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Ui\DataProvider;

use Secomm\AddressDropdown\Model\ResourceModel\RegionModel\Collection;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel\CollectionFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Locale\ListsInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Magento\Ui\DataProvider\SearchResultFactory;
use Secomm\AddressDropdown\Api\Data\RegionInterface;
use Secomm\AddressDropdown\Query\Region\GetListQuery;
use Secomm\AddressDropdown\Helper\Data as AddressDropdownHelper;

/**
 * DataProvider component.
 */
class RegionDataProvider extends DataProvider
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
     * @var array
     */
    private $loadedData = [];

    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

    /**
     * @var AddressDropdownHelper
     */
    protected AddressDropdownHelper $addressDropdownHelper;

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
     * @param DataPersistorInterface $dataPersistor
     * @param AddressDropdownHelper $addressDropdownHelper
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
        DataPersistorInterface $dataPersistor,
        AddressDropdownHelper $addressDropdownHelper,
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
        $this->dataPersistor = $dataPersistor;
        $this->addressDropdownHelper = $addressDropdownHelper;
    }

    /**
     * Returns searching result.
     *
     * @return SearchResultInterface
     */
    public function getSearchResult()
    {
        $countryId = $this->request->getParam(RegionInterface::COUNTRY_ID);

        $searchCriteria = $this->getSearchCriteria();
        $result = $this->getListQuery->execute($countryId, $searchCriteria);

        return $this->searchResultFactory->create(
            $result->getItems(),
            $result->getTotalCount(),
            $searchCriteria,
            RegionInterface::REGION_ID
        );
    }

    /**
     * Get data.
     *
     * @return array
     */
    public function getData(): array
    {
        if ($this->loadedData) {
            return $this->loadedData;
        }
        $this->loadedData = parent::getData();
        $itemsById = [];

        foreach ($this->loadedData['items'] as $item) {
            $itemsById[(int)$item[RegionInterface::REGION_ID]] = $item;
        }

        if ($this->addressDropdownHelper->isInForm($this->request->getActionName())) {
            if ($this->dataPersistor->get('entity')) {
                $this->loadedData['items'][] = $this->dataPersistor->get('entity');
                return $this->loadedData;
            }

            if (($countryId = $this->request->getParam(RegionInterface::COUNTRY_ID)) && empty($this->loadedData['items'])){
                $this->loadedData['items'][] = [
                    'region_id' => null,
                    'country_id' => $countryId,
                ];
            }
        }


        if ($id = $this->request->getParam(RegionInterface::REGION_ID)) {
            foreach ($this->addressDropdownHelper->getAllRegionNamesByRegionId($id) as $regionName) {
                $this->loadedData['items'][0]['region_name'][] = $regionName;
            }
        }

        return $this->loadedData;
    }
}
