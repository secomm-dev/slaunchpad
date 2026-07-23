<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Import;

use Magento\ImportExport\Model\Import;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Api\Data\CityInterfaceFactory;
use Secomm\AddressDropdown\Api\Data\RegionInterface;
use Secomm\AddressDropdown\Api\Data\RegionInterfaceFactory;
use Secomm\AddressDropdown\Api\Data\SubCityInterface;
use Secomm\AddressDropdown\Api\Data\SubCityInterfaceFactory;
use Secomm\AddressDropdown\Command\Region\SaveCommand as SaveCommandRegion;
use Secomm\AddressDropdown\Command\City\SaveCommand as SaveCommandCity;
use Secomm\AddressDropdown\Command\SubCity\SaveCommand as SaveCommandSubCity;
use Secomm\AddressDropdown\Model\Constant;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel\CollectionFactory;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityCollectionFactory;
use Secomm\AddressDropdown\Model\ResourceModel\SubCityModel\SubCityCollectionFactory;

/**
 * Save Address Data
 */
class SaveAddressImport
{
    /**
     * @var RegionInterfaceFactory
     */
    protected RegionInterfaceFactory $regionInterfaceFactory;

    /**
     * @var CityInterfaceFactory
     */
    protected CityInterfaceFactory $cityInterfaceFactory;

    /**
     * @var SubCityInterfaceFactory
     */
    protected SubCityInterfaceFactory $subCityInterfaceFactory;

    /**
     * @var SaveCommandRegion
     */
    protected SaveCommandRegion $saveCommandRegion;

    /**
     * @var SaveCommandCity
     */
    protected SaveCommandCity $saveCommandCity;

    /**
     * @var SaveCommandSubCity
     */
    protected SaveCommandSubCity $saveCommandSubCity;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var CollectionFactory
     */
    protected CollectionFactory $regionCollectionFactory;

    /**
     * @var CityCollectionFactory
     */
    protected CityCollectionFactory $cityCollectionFactory;

    /**
     * @var SubCityCollectionFactory
     */
    protected SubCityCollectionFactory $subCityCollectionFactory;

    /**
     * Count if created items
     *
     * @var int
     */
    protected $countItemsCreated = 0;

    /**
     * Count if updated items
     *
     * @var int
     */
    protected $countItemsUpdated = 0;

    /**
     * Count if deleted items
     *
     * @var int
     */
    protected $countItemsDeleted = 0;
    private \Secomm\AddressDropdown\Model\Import\DeleteAddressImport $deleteAddressImport;

    /**
     * @param RegionInterfaceFactory $regionInterfaceFactory
     * @param CityInterfaceFactory $cityInterfaceFactory
     * @param SubCityInterfaceFactory $subCityInterfaceFactory
     * @param SaveCommandRegion $saveCommandRegion
     * @param SaveCommandCity $saveCommandCity
     * @param SaveCommandSubCity $saveCommandSubCity
     * @param LoggerInterface $logger
     * @param CollectionFactory $regionCollectionFactory
     * @param CityCollectionFactory $cityCollectionFactory
     * @param SubCityCollectionFactory $subCityCollectionFactory
     * @param DeleteAddressImport $deleteAddressImport
     */
    public function __construct(
        RegionInterfaceFactory   $regionInterfaceFactory,
        CityInterfaceFactory     $cityInterfaceFactory,
        SubCityInterfaceFactory  $subCityInterfaceFactory,
        SaveCommandRegion        $saveCommandRegion,
        SaveCommandCity          $saveCommandCity,
        SaveCommandSubCity       $saveCommandSubCity,
        LoggerInterface          $logger,
        CollectionFactory        $regionCollectionFactory,
        CityCollectionFactory    $cityCollectionFactory,
        SubCityCollectionFactory $subCityCollectionFactory,
        DeleteAddressImport      $deleteAddressImport
    )
    {
        $this->regionInterfaceFactory = $regionInterfaceFactory;
        $this->cityInterfaceFactory = $cityInterfaceFactory;
        $this->subCityInterfaceFactory = $subCityInterfaceFactory;
        $this->saveCommandRegion = $saveCommandRegion;
        $this->saveCommandCity = $saveCommandCity;
        $this->saveCommandSubCity = $saveCommandSubCity;
        $this->logger = $logger;
        $this->regionCollectionFactory = $regionCollectionFactory;
        $this->cityCollectionFactory = $cityCollectionFactory;
        $this->subCityCollectionFactory = $subCityCollectionFactory;
        $this->deleteAddressImport = $deleteAddressImport;
    }

    /**
     * @param array $rowData
     * @return bool
     */
    public function execute(array $rowData): bool
    {
        try {
            /** @var RegionInterface $regionEntityModel */
            $regionEntityModel = $this->regionInterfaceFactory->create();
            $regionCollection = $this->regionCollectionFactory->create();

            $rowData['default_name'] = $rowData[AddressDropdown::REGION_DEFAULT_NAME] ?? '';
           if (empty($rowData[AddressDropdown::REGION_NAME]) && $rowData[AddressDropdown::LOCALE] == Constant::DEFAULT_LOCALE) {
               $rowData[AddressDropdown::REGION_NAME] = $rowData['default_name'];
           }
            if ($rowData[AddressDropdown::REGION_DEFAULT_NAME] == '') {
                return false;
            }
            $rowData['code'] = $rowData[AddressDropdown::REGION_CODE] ?? '';
            if ($rowData[AddressDropdown::REGION_DEFAULT_NAME] === Import::DEFAULT_EMPTY_ATTRIBUTE_VALUE_CONSTANT) {
                $this->deleteAddressImport->deleteByFieldAndId($regionCollection,RegionInterface::COUNTRY_ID, $rowData[RegionInterface::COUNTRY_ID]);
                return true;
            }
            $dataFilterRegion = [
                RegionInterface::CODE => $rowData['code'],
                RegionInterface::COUNTRY_ID => $rowData[AddressDropdown::ENTITY_ID_COLUMN]
            ];
            $regionId = $this->getId($regionCollection,$dataFilterRegion);
            if ($regionId) {
                $rowData[RegionInterface::REGION_ID] = (int)$regionId;
                $this->countItemsUpdated++;
            } else {
                $this->countItemsCreated++;
            }
            $regionEntityModel->addData($rowData);
            $rowData[RegionInterface::REGION_ID] = $this->saveCommandRegion->execute($regionEntityModel);

            /** @var CityInterface $cityEntityModel */
            $cityEntityModel = $this->cityInterfaceFactory->create();
            $cityCollection = $this->cityCollectionFactory->create();

            $rowData['default_name'] = $rowData[AddressDropdown::CITY_DEFAULT_NAME] ?? '';
            if ($rowData[AddressDropdown::CITY_DEFAULT_NAME] == '') {
                return true;
            }
            if ($rowData[AddressDropdown::CITY_DEFAULT_NAME] === Import::DEFAULT_EMPTY_ATTRIBUTE_VALUE_CONSTANT) {
                $this->deleteAddressImport->deleteByFieldAndId($cityCollection,CityInterface::REGION_ID, $rowData[CityInterface::REGION_ID]);
                return true;
            }
            $dataFilterCity = [
                CityInterface::DEFAULT_NAME => $rowData['default_name'],
                CityInterface::REGION_ID => $rowData[CityInterface::REGION_ID]
            ];
            $cityId = $this->getId($cityCollection, $dataFilterCity);
            if ($cityId) {
                $rowData[CityInterface::CITY_ID] = $cityId;
                $this->countItemsUpdated++;
            } else {
                $this->countItemsCreated++;
            }
            $cityEntityModel->addData($rowData);
            $rowData[CityInterface::CITY_ID] = $this->saveCommandCity->execute($cityEntityModel);

            /** @var SubCityInterface $subCityEntityModel */
            $subCityEntityModel = $this->subCityInterfaceFactory->create();
            $subCityCollection = $this->subCityCollectionFactory->create();

            $rowData['default_name'] = $rowData[AddressDropdown::SUB_CITY_DEFAULT_NAME] ?? '';
            if ($rowData[AddressDropdown::SUB_CITY_DEFAULT_NAME] == '') {
                return true;
            }
            if ($rowData[AddressDropdown::SUB_CITY_DEFAULT_NAME] === Import::DEFAULT_EMPTY_ATTRIBUTE_VALUE_CONSTANT) {
                $this->deleteAddressImport->deleteByFieldAndId($subCityCollection,SubCityInterface::CITY_ID, $rowData[SubCityInterface::CITY_ID]);
                return true;
            }
            $dataFilterSubCity = [
                SubCityInterface::DEFAULT_NAME => $rowData['default_name'],
                SubCityInterface::CITY_ID => $rowData[SubCityInterface::CITY_ID]
            ];
            $subCityId = $this->getId($subCityCollection, $dataFilterSubCity);
            if ($subCityId) {
                $rowData[SubCityInterface::SUB_CITY_ID] = $subCityId;
                $this->countItemsUpdated++;
            } else {
                $this->countItemsCreated++;
            }
            $subCityEntityModel->addData($rowData);
            $this->saveCommandSubCity->execute($subCityEntityModel);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Get ID
     *
     * @param $collection
     * @param $dataFilter
     * @return bool|int
     */
    public function getId($collection, $dataFilter): bool|int
    {
        foreach ($dataFilter as $columnName => $value) {
            if ($columnName == SubCityInterface::DEFAULT_NAME) {
                $collection->getSelect()->where($columnName . " = CONVERT(? USING binary)", $value);
            } else {
                $collection->addFieldToFilter($columnName, $value);
            }
        }

        $model = $collection->getFirstItem();

        if ($model->getId()) {
            return $model->getId();
        }

        return false;
    }

    /**
     * @return int
     */
    public function getCountItemsUpdated(): int
    {
        return $this->countItemsUpdated;
    }

    /**
     * @return int
     */
    public function getCountItemsCreated(): int
    {
        return $this->countItemsCreated;
    }
}
