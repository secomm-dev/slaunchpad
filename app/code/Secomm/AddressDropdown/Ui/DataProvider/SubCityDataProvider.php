<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Ui\DataProvider;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Magento\Ui\DataProvider\SearchResultFactory;
use Secomm\AddressDropdown\Api\Data\SubCityInterface;
use Secomm\AddressDropdown\Query\SubCity\GetListQuery;
use Secomm\AddressDropdown\Model\ResourceModel\SubCityNameModel\SubCityNameCollectionFactory;
use Secomm\AddressDropdown\Helper\Data as AddressDropdownHelper;
/**
 * DataProvider component.
 */
class SubCityDataProvider extends DataProvider
{
    /**
     * @var GetListQuery
     */
    private GetListQuery $getListQuery;

    /**
     * @var SearchResultFactory
     */
    private SearchResultFactory $searchResultFactory;

    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

    /**
     * @var SubCityNameCollectionFactory
     */
    private SubCityNameCollectionFactory $entityCollectionFactory;

    /**
     * @var array
     */
    private $loadedData = [];

    /**
     * @var AddressDropdownHelper
     */
    private AddressDropdownHelper $addressDropdownHelper;

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
     * @param DataPersistorInterface $dataPersistor
     * @param SubCityNameCollectionFactory $entityCollectionFactory
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
        DataPersistorInterface $dataPersistor,
        SubCityNameCollectionFactory $entityCollectionFactory,
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
        $this->searchResultFactory = $searchResultFactory;
        $this->dataPersistor = $dataPersistor;
        $this->entityCollectionFactory = $entityCollectionFactory;
        $this->addressDropdownHelper = $addressDropdownHelper;
    }

    /**
     * Returns searching result.
     *
     * @return SearchResultFactory
     */
    public function getSearchResult()
    {
        $cityId = $this->request->getParam(SubCityInterface::CITY_ID);
        $searchCriteria = $this->getSearchCriteria();
        $result = $this->getListQuery->execute($cityId, $searchCriteria);

        return $this->searchResultFactory->create(
            $result->getItems(),
            $result->getTotalCount(),
            $searchCriteria,
            SubCityInterface::SUB_CITY_ID
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

        if ($this->addressDropdownHelper->isInForm($this->request->getActionName())) {
            if ($this->dataPersistor->get('entity')) {
                $this->loadedData['items'][] = $this->dataPersistor->get('entity');
                return $this->loadedData;
            }
            if (($cityId = $this->request->getParam(SubCityInterface::CITY_ID)) && empty($this->loadedData['items'])){
                $this->loadedData['items'][] = [
                    SubCityInterface::SUB_CITY_ID => null,
                    SubCityInterface::CITY_ID => $cityId,
                ];
            }
        }

        if ($id = $this->request->getParam(SubCityInterface::SUB_CITY_ID)) {
            $cityNames = $this->entityCollectionFactory->create();
            $cityNames->addFieldToFilter('sub_city_id', $id);
            foreach ($cityNames->getData() as $item) {
                $this->loadedData['items'][0]['sub_city_name'][] = $item;
            }
        }

        return $this->loadedData;
    }
}
