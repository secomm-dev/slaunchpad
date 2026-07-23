<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Command\SubCity;

use Exception;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Api\Data\SubCityInterface;
use Secomm\AddressDropdown\Api\Data\SubCityNameInterface;
use Secomm\AddressDropdown\Model\Constant;
use Secomm\AddressDropdown\Model\ResourceModel\SubCityModel\SubCityCollection;
use Secomm\AddressDropdown\Model\ResourceModel\SubCityModel\SubCityCollectionFactory;
use Secomm\AddressDropdown\Model\ResourceModel\SubCityNameModel\SubCityNameCollectionFactory;
use Secomm\AddressDropdown\Model\ResourceModel\SubCityNameResource;
use Secomm\AddressDropdown\Model\ResourceModel\SubCityResource;
use Secomm\AddressDropdown\Model\SubCityModel;
use Secomm\AddressDropdown\Model\SubCityModelFactory;

/**
 * Save SubCity Command.
 */
class SaveCommand
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var SubCityModelFactory
     */
    private SubCityModelFactory $modelFactory;

    /**
     * @var SubCityResource
     */
    private SubCityResource $resource;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var SubCityNameResource
     */
    private SubCityNameResource $subCityNameResource;

    /**
     * @var SubCityNameCollectionFactory
     */
    private SubCityNameCollectionFactory $subcityNameCollectionFactory;

    /**
     * @var SubCityCollectionFactory
     */
    private SubCityCollectionFactory $subCityCollectionFactory;

    /**
     * @param LoggerInterface $logger
     * @param SubCityModelFactory $modelFactory
     * @param SubCityResource $resource
     * @param SubCityCollectionFactory $subCityCollectionFactory
     * @param SubCityNameResource $subCityNameResource
     * @param SubCityNameCollectionFactory $subcityNameCollectionFactory
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        LoggerInterface              $logger,
        SubCityModelFactory          $modelFactory,
        SubCityResource              $resource,
        SubCityCollectionFactory     $subCityCollectionFactory,
        SubCityNameResource          $subCityNameResource,
        SubCityNameCollectionFactory $subcityNameCollectionFactory,
        ResourceConnection           $resourceConnection
    )
    {
        $this->logger = $logger;
        $this->modelFactory = $modelFactory;
        $this->resource = $resource;
        $this->subCityCollectionFactory = $subCityCollectionFactory;
        $this->subCityNameResource = $subCityNameResource;
        $this->subcityNameCollectionFactory = $subcityNameCollectionFactory;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Save SubCity.
     *
     * @param SubCityInterface $subCity
     *
     * @return int
     * @throws CouldNotSaveException
     * @throws InvalidArgumentException
     */
    public function execute(SubCityInterface $subCity): int
    {
        try {
            /** @var SubCityModel $model */
            $model = $this->modelFactory->create();
            $model->addData($subCity->getData());
            $model->setHasDataChanges(true);

            if (!$model->getData(SubCityInterface::SUB_CITY_ID)) {
                $model->isObjectNew(true);
            }
            $this->resource->save($model);

            // Save Region Name from Form Admin
            $subCityNames = $model->getSubCityName();
            if (is_object($subCityNames) || is_array($subCityNames)) {
                $this->checkAndDeleteSubCityNames($subCityNames, (int)$model->getData(SubCityInterface::SUB_CITY_ID));
                foreach ($subCityNames as $cityName) {
                    $cityName['sub_city_id'] = (int)$model->getData(SubCityInterface::SUB_CITY_ID);
                    unset($cityName['record_id']);
                    unset($cityName['initialize']);
                    $this->setSubCityName($cityName);
                }
            }

            // Save Form File Import
            if (is_string($subCityNames) && $subCityNames !== '') {
                $subCityNameData = [];
                $subCityNameData['name'] = $subCityNames;
                $subCityNameData['sub_city_id'] = (int)$model->getData(SubCityInterface::SUB_CITY_ID);
                $subCityNameData['locale'] = $model->getData(SubCityNameInterface::LOCALE);
                $this->setSubCityName($subCityNameData);
            }

            // Remove all City Names locale
            if (is_null($subCityNames) && !is_string($subCityNames)) {
                $this->checkAndDeleteSubCityNames([], (int)$model->getData(SubCityInterface::SUB_CITY_ID));
            }

            // add Default Name and Locale "en_US"
            $this->addDefaultNameAndLocale((int)$model->getData(SubCityInterface::SUB_CITY_ID));
        } catch (Exception $exception) {
            $this->logger->error(
                __('Could not save SubCity. Original message: {message}'),
                [
                    'message' => $exception->getMessage(),
                    'exception' => $exception
                ]
            );
            throw new CouldNotSaveException(__('Could not save SubCity.'));
        }

            return (int)$model->getData(SubCityInterface::SUB_CITY_ID);
    }

    /**
     * @param array $subCityNames
     * @param $subCityId
     * @return void
     */
    protected function checkAndDeleteSubCityNames(array $subCityNames, $subCityId): void
    {
        try {
            $allSubCityNameExist = $this->subcityNameCollectionFactory->create()->addFieldToFilter('sub_city_id', $subCityId);
            $listLocaleExist = array_column($allSubCityNameExist->getData(), 'locale');
            $listLocaleNew = array_column($subCityNames, 'locale');
            $diff = array_diff($listLocaleExist, $listLocaleNew);
            $key = array_search(Constant::DEFAULT_LOCALE, $diff, true);
            if (is_int($key)) {
                unset($diff[$key]);
            }
            foreach ($diff as $locale) {
                $where = ["`locale` = '" . $locale . "' AND `sub_city_id` =" . $subCityId];
                $this->resourceConnection->getConnection()
                    ->delete(
                        $this->subCityNameResource->getMainTable(),
                        $where
                    );
            }
        } catch (Exception $e) {
            return;
        }
    }

    /**
     * @param $subCityId
     * @return void
     * @throws CouldNotSaveException
     */
    public function addDefaultNameAndLocale($subCityId): void
    {
        try {
            $subcityNameCollection = $this->subcityNameCollectionFactory->create();
            $subcityNameCollection->addFieldToFilter(SubCityNameInterface::LOCALE, Constant::DEFAULT_LOCALE)
                ->addFieldToFilter(SubCityNameInterface::SUB_CITY_ID, $subCityId);
            if (!$subcityNameCollection->count()) {
                $model = $this->modelFactory->create();
                $this->resource->load($model, $subCityId);
                $subCityNameData['name'] = $model->getDefaultName();
                $subCityNameData['sub_city_id'] = $subCityId;
                $subCityNameData['locale'] = Constant::DEFAULT_LOCALE;
                $this->setSubCityName($subCityNameData);
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * @param mixed $subCityName
     * @return void
     * @throws CouldNotSaveException
     */
    private function setSubCityName(mixed $subCityName): void
    {
        try {
            $this->resourceConnection->getConnection()
                ->insertOnDuplicate(
                    $this->resourceConnection->getTableName('directory_city_sub_city_name'),
                    $subCityName,
                    [SubCityNameInterface::NAME, SubCityNameInterface::LOCALE]
                );
        } catch (Exception $e) {
            throw new CouldNotSaveException(__('Could not save SubCityName.'));
        }
    }
}
