<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Export;

use Exception;
use Magento\Directory\Model\ResourceModel\Country\Collection as CountryCollection;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\ImportExport\Model\Export;
use Magento\ImportExport\Model\Export\AbstractEntity;
use Magento\ImportExport\Model\Export\Adapter\CsvFactory;
use Magento\ImportExport\Model\Export\Factory;
use Magento\ImportExport\Model\ResourceModel\CollectionByPagesIteratorFactory;
use Magento\Store\Model\StoreManagerInterface;
use Secomm\AddressDropdown\Api\Data\RegionInterface;
use Secomm\AddressDropdown\Model\RegionFactory;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityCollectionFactory;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel\CollectionFactory as RegionCollectionFactory;

/**
 * Class AddressDropdown
 * @package Secomm\AddressDropdown\Model\Export
 */
class AddressDropdown extends AbstractEntity
{
    /**
     * Const entity
     */
    const ADDRESS_DROPDOWN_ENTITY = 'address_dropdown';
    const PATH_EXPORT = 'export/address_dropdown.csv';
    const COLUMN_COUNTRY_ID = 'country_id';
    const REGION_ID = 'region_id';
    const LOCALE = 'locale';
    const CODE_REGION = 'code_region';
    const REGION_DEFAULT_NAME = 'region_default_name';
    const REGION_NAME = 'region_name';
    const CITY_DEFAULT_NAME = 'city_default_name';
    const CITY_NAME = 'city_name';

    /**
     * Permanent entity columns
     *
     * @var string[]
     */
    protected $_permanentAttributes = [
        self::LOCALE,
        self::COLUMN_COUNTRY_ID,
        self::CODE_REGION,
        self::REGION_DEFAULT_NAME,
        self::REGION_NAME,
        self::CITY_DEFAULT_NAME,
        self::CITY_NAME,
    ];

    /**
     * @var array
     */
    protected $_parameters = [
        self::LOCALE,
        self::COLUMN_COUNTRY_ID,
        self::CODE_REGION,
        self::REGION_DEFAULT_NAME,
        self::REGION_NAME,
        self::CITY_DEFAULT_NAME,
        self::CITY_NAME,
    ];

    /**
     * @var CsvFactory
     */
    private CsvFactory $csvFactory;

    /**
     * @var CountryCollectionFactory
     */
    private CountryCollectionFactory $countryCollectionFactory;

    /**
     * @var AttributeCollectionProvider
     */
    private AttributeCollectionProvider $attributeCollectionProvider;

    /**
     * @var RegionFactory
     */
    private RegionFactory $regionFactory;

    /**
     * @var RegionModel
     */
    private RegionModel $regionModel;

    /**
     * @var CityCollectionFactory
     */
    private CityCollectionFactory $cityCollectionFactory;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param Factory $collectionFactory
     * @param CollectionByPagesIteratorFactory $resourceColFactory
     * @param CsvFactory $csvFactory
     * @param CountryCollectionFactory $countryCollectionFactory
     * @param CityCollectionFactory $cityCollectionFactory
     * @param AttributeCollectionProvider $attributeCollectionProvider
     * @param RegionFactory $regionFactory
     * @param RegionModel $regionModel
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface             $scopeConfig,
        StoreManagerInterface            $storeManager,
        Factory                          $collectionFactory,
        CollectionByPagesIteratorFactory $resourceColFactory,
        CsvFactory                       $csvFactory,
        CountryCollectionFactory         $countryCollectionFactory,
        CityCollectionFactory            $cityCollectionFactory,
        AttributeCollectionProvider      $attributeCollectionProvider,
        RegionFactory                    $regionFactory,
        RegionModel                      $regionModel,
        array                            $data = []
    )
    {
        parent::__construct($scopeConfig, $storeManager, $collectionFactory, $resourceColFactory, $data);
        $this->csvFactory = $csvFactory;
        $this->countryCollectionFactory = $countryCollectionFactory;
        $this->cityCollectionFactory = $cityCollectionFactory;
        $this->attributeCollectionProvider = $attributeCollectionProvider;
        $this->regionFactory = $regionFactory;
        $this->regionModel = $regionModel;
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function export(): string
    {
        //Execution time may be very long
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        set_time_limit(0);

        $writer = $this->csvFactory->create(['destination' => self::PATH_EXPORT]);
        $writer->setHeaderCols($this->_getHeaderColumns());

        $exportFilter = !empty($this->_parameters[Export::FILTER_ELEMENT_GROUP]) ?
            $this->_parameters[Export::FILTER_ELEMENT_GROUP] : [];

        $collection = $this->_getEntityCollection();
        $collection = $this->prepareCollection($collection, $exportFilter);
        foreach ($collection->getData() as $item) {
            $data = $this->prepareData($item);
            $writer->writeRow($data);
        }

        return $writer->getContents();
    }

    /**
     * @return array|string[]
     */
    protected function _getHeaderColumns(): array
    {
        $skipAttr = $this->_parameters[Export::FILTER_ELEMENT_SKIP];
        if (in_array(self::COLUMN_COUNTRY_ID, $skipAttr)) {
            unset($this->_permanentAttributes[array_search(self::COLUMN_COUNTRY_ID, $this->_permanentAttributes)]);
        }
        if (in_array(self::REGION_ID, $skipAttr)) {
            unset($this->_permanentAttributes[array_search(self::CODE_REGION, $this->_permanentAttributes)]);
        }
        return $this->_permanentAttributes;
    }

    /**
     * Get Entity Collection Address
     *
     * @return AbstractDb|CountryCollection
     */
    protected function _getEntityCollection(): CountryCollection|AbstractDb
    {
        $countryCollection = null;
        $cityCollection = null;
        $countryCollection = $this->countryCollectionFactory->create();

        // City and City Name table left join
        $cityCollection = $this->cityCollectionFactory->create();
        $cityCollection->getSelect()
            ->joinLeft(
                ['cn' => 'directory_region_city_name'],
                "`main_table`.city_id = `cn`.city_id",
                ["city_name" => "cn.name", "city_name_locale" => "cn.locale"]
            );

        $countryCollection->getSelect()
            ->joinLeft(
                ['r' => 'directory_country_region'],
                "`main_table`.country_id = `r`.country_id",
                ["region_default_name" => "r.default_name", "r.code"])
            ->joinLeft(
                ['rn' => 'directory_country_region_name'],
                "`r`.region_id = `rn`.region_id",
                ["region_name" => "rn.name", "rn.locale"])
            ->joinLeft(
                ['city' => $cityCollection->getSelect()],
                "`r`.region_id = `city`.region_id AND `rn`.locale = `city`.city_name_locale",
                ["city_default_name" => "city.default_name", "city_name" => "city.city_name"]);

        return $countryCollection;
    }

    protected function prepareCollection($collection, $exportFilter)
    {
        $logger = ObjectManager::getInstance()->get('\Psr\Log\LoggerInterface');
        foreach ($exportFilter as $columnName => $columnValue) {
            $logger->error('$exportFilter ' . json_encode($exportFilter));
            $logger->error('$columnName ' . json_encode($columnName));
            $logger->error('$columnValue ' . json_encode($columnValue));
            if ($columnName == self::COLUMN_COUNTRY_ID && $columnValue !== '') {
                $collection->addFieldToFilter("main_table." . $columnName, ['like' => '%' . $columnValue . '%']);
            }
            if ($columnName == self::REGION_ID && $columnValue !== '') {
                $regionName = $this->getRegionName($columnValue);
                $collection->addFieldToFilter("r." . RegionInterface::DEFAULT_NAME, ['like' => '%' . $regionName . '%']);
            }
        }

        return $collection;
    }

    /**
     * Get Region default name by region name
     *
     * @param $columnValue
     * @return string
     */
    private function getRegionName($columnValue): string
    {
        $regionModel = $this->regionFactory->create();
        $this->regionModel->load($regionModel, $columnValue);
        return (string)$regionModel->getData(RegionInterface::DEFAULT_NAME);
    }

    /**
     * @param $item
     * @return array
     */
    protected function prepareData($item): array
    {
        if (is_null($item[self::CITY_NAME]) || empty($item[self::CITY_NAME])) {
            $item[self::CITY_DEFAULT_NAME] = '';
        }
        return [
            self::LOCALE => $item[self::LOCALE] ?? '',
            self::COLUMN_COUNTRY_ID => $item[self::COLUMN_COUNTRY_ID] ?? '',
            self::CODE_REGION => $item['code'] ?? '',
            self::REGION_DEFAULT_NAME => $item[self::REGION_DEFAULT_NAME] ?? '',
            self::REGION_NAME => $item[self::REGION_NAME] ?? '',
            self::CITY_DEFAULT_NAME => $item[self::CITY_DEFAULT_NAME] ?? '',
            self::CITY_NAME => $item[self::CITY_NAME] ?? '',
        ];
    }

    /**
     * @param AbstractModel $item
     *
     */
    public function exportItem($item): array
    {
        return [];
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function getEntityTypeCode(): string
    {
        return self::ADDRESS_DROPDOWN_ENTITY;
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function getAttributeCollection(): Collection
    {
        return $this->attributeCollectionProvider->get();
    }
}
