<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Helper;

use Magento\Customer\Api\Data\RegionInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Locale\Config;
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Api\Data\SubCityInterface;
use Secomm\AddressDropdown\Model\OptionSource\SourceItemLocale;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel;
use Secomm\AddressDropdown\Model\Region;
use Secomm\AddressDropdown\Model\RegionFactory;
use Secomm\AddressDropdown\Model\ResourceModel\CityResource as CityResourceModel;
use Secomm\AddressDropdown\Model\CityModel;
use Secomm\AddressDropdown\Model\CityModelFactory;
use Secomm\AddressDropdown\Model\ResourceModel\SubCityResource  as SubCityResourceModel;
use Secomm\AddressDropdown\Model\SubCityModel;
use Secomm\AddressDropdown\Model\SubCityModelFactory;
use Secomm\AddressDropdown\Api\Data\RegionInterface as RegionInterfaceAddress;

class Data extends AbstractHelper
{
    const XML_PATH_ADDRESS = 'address/general/enable';

    /**
     * @param ResourceConnection $resourceConnection
     * @param Config $localeConfig
     * @param SourceItemLocale $sourceItemLocale
     * @param CountryCollectionFactory $countryCollectionFactory
     * @param RegionModel $regionModel
     * @param RegionFactory $regionFactory
     * @param CityResourceModel $cityResourceModel
     * @param CityModelFactory $cityModelFactory
     * @param SubCityResourceModel $subCityResourceModel
     * @param SubCityModelFactory $subCityModelFactory
     * @param Context $context
     */
    public function __construct(
        protected ResourceConnection     $resourceConnection,
        protected Config                 $localeConfig,
        protected SourceItemLocale       $sourceItemLocale,
        protected CountryCollectionFactory $countryCollectionFactory,
        protected RegionModel            $regionModel,
        protected RegionFactory          $regionFactory,
        protected CityResourceModel      $cityResourceModel,
        protected CityModelFactory       $cityModelFactory,
        protected SubCityResourceModel   $subCityResourceModel,
        protected SubCityModelFactory    $subCityModelFactory,
        Context $context
    )
    {
        parent::__construct($context);
    }

    /**
     * @param string $field
     * @param null $storeId
     * @return mixed
     */
    public function getConfigValue($field, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $field, ScopeInterface::SCOPE_STORE, $storeId
        );
    }

    /**
     * @param null $storeId
     * @return bool
     */
    public function isAddressDropdownModuleEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ADDRESS, ScopeInterface::SCOPE_STORE, $storeId
        );
    }

    /**
     * @param $regionId
     * @return array|null
     */
    public function getAllRegionNamesByRegionId($regionId) : ?array
    {
        $connect = $this->resourceConnection->getConnection();
        $sql = 'SELECT * FROM `'.$connect->getTableName('directory_country_region_name').'` WHERE `'.RegionInterface::REGION_ID.'` = '. $regionId .' ORDER BY `'.RegionInterface::REGION_ID.'` DESC';
        return $connect->fetchAll($sql);
    }

    /**
     * @param $locale
     * @return bool
     */
    public function hasLocale($locale): bool
    {
        $locales = $this->sourceItemLocale->toOptionArray();
        foreach ($locales as $localeItem) {
            if ($localeItem['value'] === $locale) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $countryCode
     * @return bool
     */
    public function hasCountryId($countryCode): bool
    {
        /** @var $collection \Magento\Directory\Model\ResourceModel\Country\Collection */
        $collection = $this->countryCollectionFactory->create();
        foreach ($collection->getData() as $row) {
            $iso2Countries[$row['iso2_code']] = $row['country_id'];
        }
        return isset($iso2Countries[$countryCode]);
    }

    /**
     * Get country id by region id
     *
     * @param $regionId
     * @return string
     */
    public function getCountryIdByRegionId($regionId): string
    {
        /** @var Region $regionModel */
        $regionModel = $this->regionFactory->create();
        $this->regionModel->load($regionModel, $regionId, CityInterface::REGION_ID);

        if ((int)$regionModel->getId() !== (int)$regionId) {
            return '';
        }

        return (string)$regionModel->getData(RegionInterfaceAddress::COUNTRY_ID);
    }

    /**
     * @param $regionId
     * @return Region|null
     */
    public function getRegionById($regionId): ?Region
    {
        /** @var Region $regionModel */
        $regionModel = $this->regionFactory->create();
        $this->regionModel->load($regionModel, $regionId, CityInterface::REGION_ID);

        if ((int)$regionModel->getId() !== (int)$regionId) {
            return null;
        }

        return $regionModel;
    }

    /**
     * Get region id by city id
     *
     * @param $cityId
     * @return int
     */
    public function getRegionIdByCityId($cityId): int
    {
        /** @var CityModel $modelFactory */
        $modelFactory = $this->cityModelFactory->create();
        $this->cityResourceModel->load($modelFactory, (int)$cityId, CityInterface::CITY_ID);

        if ((int)$modelFactory->getId() !== (int)$cityId) {
            return 0;
        }

        return (int)$modelFactory->getData(RegionInterfaceAddress::REGION_ID);
    }

    /**
     * Get city id by sub city id
     *
     * @param $subCityId
     * @return int
     */
    public function getCityIdBySubCityId($subCityId): int
    {
        /** @var SubCityModel $modelFactory */
        $modelFactory = $this->subCityModelFactory->create();
        $this->subCityResourceModel->load($modelFactory, $subCityId, SubCityInterface::SUB_CITY_ID);

        if ((int)$modelFactory->getId() !== (int)$subCityId) {
            return 0;
        }

        return (int)$modelFactory->getData(SubCityInterface::CITY_ID);
    }

    /**
     * Check is In Form to add new or edit
     *
     * @param $actionName
     * @return bool
     */
    public function isInForm($actionName): bool
    {
        $flag = false;
        $actionForm = ['edit', 'new'];
        if (in_array($actionName, $actionForm)) {
            $flag = true;
        }

        return $flag;
    }
}
