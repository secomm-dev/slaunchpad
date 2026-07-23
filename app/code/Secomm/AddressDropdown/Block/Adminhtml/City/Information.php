<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Block\Adminhtml\City;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Template\Context;
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Model\CityModelFactory;
use Secomm\AddressDropdown\Model\ResourceModel\CityResource as CityModelResourceModel;
use Secomm\AddressDropdown\Model\ResourceModel\CityNameModel\CityNameCollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Locale\ListsInterface;
use Magento\Framework\View\Element\Template;

/**
 * Get Information of City
 */
class Information extends Template
{
    /** @var string */
    protected $_template = 'Secomm_AddressDropdown::city/information.phtml';

    /**
     * @param Context $context
     * @param DataPersistorInterface $dataPersistor
     * @param ListsInterface $localeLists
     * @param CityModelFactory $cityModelFactory
     * @param CityModelResourceModel $cityResourceModel
     * @param CityNameCollectionFactory $cityNameCollection
     * @param ResourceConnection $resourceConnection
     * @param array $data
     */
    public function __construct(
        Template\Context                    $context,
        protected DataPersistorInterface    $dataPersistor,
        protected ListsInterface            $localeLists,
        protected CityModelFactory          $cityModelFactory,
        protected CityModelResourceModel    $cityResourceModel,
        protected CityNameCollectionFactory $cityNameCollection,
        protected ResourceConnection        $resourceConnection,
        array                               $data = []
    )
    {
        parent::__construct($context, $data);
    }

    /**
     * Return Country ID
     * @return mixed|null
     */
    protected function getCityId()
    {
        return $this->dataPersistor->get('city_id');
    }

    /**
     * Get CityModel
     * @return \Secomm\AddressDropdown\Model\CityModel|null
     */
    public function getCityModel()
    {
        try {
            $cityId = $this->getCityId();
            if (isset($cityId)) {
                return $this->cityModelFactory->create()->load($cityId);
            }
            return null;
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * @return array|null
     */
    public function getAllCityNamesByCityId(): ?array
    {
        try {
            $cityId = $this->getCityId();
            $cityNamesCollectData = $this->cityNameCollection->create();

            $cityNamesCollectData->addFieldToFilter(
                CityInterface::CITY_ID,
                ['eq' => $cityId]
            );
            return $cityNamesCollectData->getData();
        } catch (\Exception $e) {
            return null;
        }
    }
}
