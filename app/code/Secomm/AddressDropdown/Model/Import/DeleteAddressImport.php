<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Import;

use Exception;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Api\Data\RegionInterface;
use Secomm\AddressDropdown\Api\Data\RegionInterfaceFactory;
use Secomm\AddressDropdown\Api\Data\SubCityInterface;
use Secomm\AddressDropdown\Model\Constant;
use Magento\Framework\App\ResourceConnection;
use Secomm\AddressDropdown\Helper\Data;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel\Collection;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel\CollectionFactory as RegionCollectionFactory;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityCollectionFactory;
use Secomm\AddressDropdown\Command\Region\DeleteByIdCommand;
use Secomm\AddressDropdown\Command\City\DeleteByIdCommand as CityDeleteByIdCommand;
use Secomm\AddressDropdown\Command\SubCity\DeleteByIdCommand as SubCityDeleteByIdCommand;

/**
 * Delete Address Data
 */
class DeleteAddressImport
{
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var RegionCollectionFactory
     */
    private RegionCollectionFactory $collectionFactory;

    /**
     * @var CityCollectionFactory
     */
    protected CityCollectionFactory $cityCollectionFactory;

    /**
     * @var DeleteByIdCommand
     */
    private DeleteByIdCommand $deleteByIdCommand;

    /**
     * @var CityDeleteByIdCommand
     */
    private CityDeleteByIdCommand $cityDeleteByIdCommand;

    /**
     * @var SubCityDeleteByIdCommand
     */
    private SubCityDeleteByIdCommand $subCityDeleteByIdCommand;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var Data
     */
    protected Data $data;

    /**
     * @param RegionCollectionFactory $collectionFactory
     * @param CityCollectionFactory $cityCollectionFactory
     * @param DeleteByIdCommand $deleteByIdCommand
     * @param CityDeleteByIdCommand $cityDeleteByIdCommand
     * @param SubCityDeleteByIdCommand $subCityDeleteByIdCommand
     * @param ResourceConnection $resourceConnection
     * @param Data $data
     * @param LoggerInterface $logger
     */
    public function __construct(
        RegionCollectionFactory  $collectionFactory,
        CityCollectionFactory    $cityCollectionFactory,
        DeleteByIdCommand        $deleteByIdCommand,
        CityDeleteByIdCommand    $cityDeleteByIdCommand,
        SubCityDeleteByIdCommand $subCityDeleteByIdCommand,
        ResourceConnection       $resourceConnection,
        Data                     $data,
        LoggerInterface          $logger
    )
    {
        $this->collectionFactory = $collectionFactory;
        $this->cityCollectionFactory = $cityCollectionFactory;
        $this->deleteByIdCommand = $deleteByIdCommand;
        $this->cityDeleteByIdCommand = $cityDeleteByIdCommand;
        $this->subCityDeleteByIdCommand = $subCityDeleteByIdCommand;
        $this->resourceConnection = $resourceConnection;
        $this->data = $data;
        $this->logger = $logger;
    }

    /**
     * @param $countryId
     * @param $regionCode
     * @return int
     */
    public function execute($countryId, $regionCode): int
    {
        try {
            /** @var Collection $colectionModel */
            $collectionModel = $this->collectionFactory->create();
            $collectionModel->addFieldToFilter(RegionInterface::COUNTRY_ID, $countryId)
                ->addFieldToFilter(RegionInterface::CODE, $regionCode);

            $regionModel = $collectionModel->getFirstItem();
            if (is_null($regionModel)) {
                return false;
            }
            $isDefaultRegion = $regionModel->getData(RegionInterface::IS_DEFAULT);
            $regionId = (int)$regionModel->getData(RegionInterface::REGION_ID);
            if ($regionId) {
                if ((int)$isDefaultRegion) {
                    $cityCollection = $this->cityCollectionFactory->create();
                    $this->deleteByFieldAndId($cityCollection, CityInterface::REGION_ID, $regionId);
                    $this->deleteRegionNames($regionId);
                    return true;
                }
                $this->deleteByIdCommand->execute($regionId);
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * @param $collectionModel
     * @param $field
     * @param $id
     * @return bool
     */
    public function deleteByFieldAndId($collectionModel, $field, $id): bool
    {
        try {
            $collectionModel->addFieldToFilter($field, $id);

            foreach ($collectionModel->getData() as $item) {
                if ($field === CityInterface::REGION_ID) {
                    $this->cityDeleteByIdCommand->execute($item[CityInterface::CITY_ID]);
                }
                if ($field === SubCityInterface::CITY_ID) {
                    $this->subCityDeleteByIdCommand->execute($item[SubCityInterface::SUB_CITY_ID]);
                }
                if ($field === RegionInterface::COUNTRY_ID) {
                    $this->deleteByIdCommand->execute($item[RegionInterface::REGION_ID]);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return true;
    }

    /**
     * Delete all locale except en_US
     *
     * @param $regionId
     * @return bool
     */
    protected function deleteRegionNames($regionId): bool
    {
        try {
            $allRegionNameExist = $this->data->getAllRegionNamesByRegionId($regionId);
            $listLocaleExist = array_column($allRegionNameExist, 'locale');
            $key = array_search(Constant::DEFAULT_LOCALE, $listLocaleExist, true);
            if (is_int($key)) {
                unset($listLocaleExist[$key]);
            }

            foreach ($listLocaleExist as $locale) {
                $where = ["`locale` = '" . $locale . "' AND `region_id` =" . $regionId];
                $this->resourceConnection->getConnection()
                    ->delete(
                        $this->resourceConnection->getTableName(Constant::DIRECTORY_COUNTRY_REGION_NAME),
                        $where
                    );
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
        return true;
    }
}
