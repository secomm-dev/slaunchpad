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
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Query\City\GetListQuery;
use Secomm\AddressDropdown\Helper\Data as AddressDropdownHelper;
use Secomm\AddressDropdown\Model\ResourceModel\CityNameModel\CityNameCollectionFactory;

/**
 * DataProvider component.
 */
class CityDataProvider extends DataProvider
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
     * @var CityNameCollectionFactory
     */
    private CityNameCollectionFactory $entityCollectionFactory;

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
     * @param CityNameCollectionFactory $entityCollectionFactory
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
        CityNameCollectionFactory     $entityCollectionFactory,
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
        $regionId = $this->request->getParam(CityInterface::REGION_ID);

        $searchCriteria = $this->getSearchCriteria();
        $result = $this->getListQuery->execute($regionId, $searchCriteria);

        return $this->searchResultFactory->create(
            $result->getItems(),
            $result->getTotalCount(),
            $searchCriteria,
            CityInterface::CITY_ID
        );
    }

    /**
     * TASK-9EX975 Slice B: when the form is opened via the grid "Add child" action, the
     * parent is locked (readonly-context) — the admin picked it explicitly from the row.
     *
     * @return array
     */
    public function getMeta(): array
    {
        $meta = parent::getMeta();
        if ($this->request->getActionName() === 'new'
            && $this->request->getParam(CityInterface::PARENT_CITY_ID)
        ) {
            $meta['general']['children']['parent_city_id']['arguments']['data']['config']['disabled'] = true;
        }

        return $meta;
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
            if (($regionId = $this->request->getParam(CityInterface::REGION_ID)) && empty($this->loadedData['items'])){
                // TASK-9EX975 Slice B: "Add child" opens the form with the parent locked in.
                $this->loadedData['items'][] = [
                    CityInterface::CITY_ID => null,
                    CityInterface::REGION_ID => $regionId,
                    CityInterface::PARENT_CITY_ID => $this->request->getParam(CityInterface::PARENT_CITY_ID),
                ];
            }
        }

        if ($id = $this->request->getParam(CityInterface::CITY_ID)) {
            $cityNames = $this->entityCollectionFactory->create();
            $cityNames->addFieldToFilter('city_id', $id);
            foreach ($cityNames->getData() as $item) {
                $this->loadedData['items'][0]['city_name'][] = $item;
            }
        }

        return $this->loadedData;
    }
}
