<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Command\Region;

use Exception;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Api\Data\RegionInterface;
use Secomm\AddressDropdown\Helper\Data;
use Secomm\AddressDropdown\Model\Constant;
use Secomm\AddressDropdown\Model\Region;
use Secomm\AddressDropdown\Model\RegionFactory;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel as RegionModelResourceModel;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel\Collection as RegionModelCollection;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel\CollectionFactory as RegionCollectionFactory;

/**
 * Save Region Command.
 */
class SaveCommand
{
    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resourceConnection;
    /**
     * @var Data
     */
    protected Data $data;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    /**
     * @var RegionFactory
     */
    private RegionFactory $modelFactory;
    /**
     * @var RegionModelResourceModel
     */
    private RegionModelResourceModel $resource;
    /**
     * @var RegionCollectionFactory
     */
    private RegionCollectionFactory $collectionFactory;

    /**
     * @param LoggerInterface $logger
     * @param RegionFactory $modelFactory
     * @param RegionModelResourceModel $resource
     * @param RegionCollectionFactory $collectionFactory
     * @param ResourceConnection $resourceConnection
     * @param Data $data
     */
    public function __construct(
        LoggerInterface          $logger,
        RegionFactory            $modelFactory,
        RegionModelResourceModel $resource,
        RegionCollectionFactory  $collectionFactory,
        ResourceConnection       $resourceConnection,
        Data                     $data
    )
    {
        $this->logger = $logger;
        $this->modelFactory = $modelFactory;
        $this->resource = $resource;
        $this->collectionFactory = $collectionFactory;
        $this->resourceConnection = $resourceConnection;
        $this->data = $data;
    }

    /**
     * Save Region.
     *
     * @param RegionInterface $region
     * @return int
     * @throws CouldNotSaveException
     * @throws InvalidArgumentException
     */
    public function execute(RegionInterface $region): int
    {
        try {
            $this->validate($region);
        } catch (Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()));
        }

        try {
            /** @var Region $model */
            $model = $this->modelFactory->create();
            $model->addData($region->getData());
            $model->setHasDataChanges(true);

            if (!$model->getData(RegionInterface::REGION_ID)) {
                $model->setData('is_default', 0); // Set 0 to edit and delete
                $model->isObjectNew(true);
            }
            if (!$region->getIsDefault()) {
                $this->resource->save($model);
            }

            // Save Region Name from Form Admin
            $regionNames = $model->getRegionName();
            if (is_object($regionNames) || is_array($regionNames)) {
                $this->checkAndDeleteRegionNames($regionNames, (int)$model->getData(RegionInterface::REGION_ID));
                foreach ($regionNames as $regionName) {
                    $regionName['region_id'] = (int)$model->getData(RegionInterface::REGION_ID);
                    unset($regionName['record_id']);
                    unset($regionName['initialize']);
                    $this->setRegionName($regionName);
                }
            }

            // Save Region Name From File Import
            if (is_string($regionNames) && $regionNames !== '') {
                if (!$this->data->hasLocale($region->getLocale())) {
                    throw new CouldNotSaveException(__('Could not save Region Name with locale %1.', $region->getLocale()));
                }
                $regionNameData['name'] = $regionNames;
                $regionNameData['region_id'] = (int)$model->getData(RegionInterface::REGION_ID);
                $regionNameData['locale'] = $model->getData(RegionInterface::LOCALE);
                $this->setRegionName($regionNameData);
            }

            // Remove all Region Names locale
            if (is_null($regionNames) && !is_string($regionNames)) {
                $this->checkAndDeleteRegionNames([], (int)$model->getData(RegionInterface::REGION_ID));
            }

            // add Default Name and Locale "en_US"
            $this->addDefaultNameAndLocale((int)$model->getData(RegionInterface::REGION_ID));
        } catch (Exception $exception) {
            $this->logger->error(
                __('Could not save Region. Original message: {message}'),
                [
                    'message' => $exception->getMessage(),
                    'exception' => $exception
                ]
            );
            throw new CouldNotSaveException(__('Could not save Region.'));
        }

        return (int)$model->getData(RegionInterface::REGION_ID);
    }

    /**
     * Validate Model
     *
     * @param RegionInterface $region
     * @throws InvalidArgumentException
     */
    public function validate(RegionInterface $region): void
    {
        if (!$this->data->hasCountryId($region->getCountryId())) {
            throw new InvalidArgumentException(__('Country ID does not exist.'));
        }

        /** @var RegionModelCollection $modelCollectionFactory */
        $modelCollectionFactory = $this->collectionFactory->create();
        $modelCollectionFactory->addFieldToFilter(RegionInterface::CODE, $region->getCode())
            ->addFieldToFilter(RegionInterface::COUNTRY_ID, $region->getCountryId());
        $dataRegionCheck = $modelCollectionFactory->getFirstItem();

        $regionId = (int)$dataRegionCheck->getData(RegionInterface::REGION_ID);
        $countryId = $dataRegionCheck->getData(RegionInterface::COUNTRY_ID);
        if ($dataRegionCheck->getId() && $region->getCountryId() === $countryId && ($region->getRegionId() !== $regionId)) {
            throw new InvalidArgumentException(__('Code already existed.'));
        }

        if ($region->getCode() === '') {
            throw new InvalidArgumentException(__('Code is required.'));
        }

        if ($region->getDefaultName() === '') {
            throw new InvalidArgumentException(__('Default Name is required.'));
        }
        if ($dataRegionCheck->getData(RegionInterface::IS_DEFAULT)) {
            $region->setIsDefault(1);
        }
    }

    /**
     * @param array $regionNames
     * @param $regionId
     * @return void
     */
    protected function checkAndDeleteRegionNames(array $regionNames, $regionId): void
    {
        try {
            $allRegionNameExist = $this->data->getAllRegionNamesByRegionId($regionId);
            $listLocaleExist = array_column($allRegionNameExist, 'locale');
            $listLocaleNew = array_column($regionNames, 'locale');
            $diff = array_diff($listLocaleExist, $listLocaleNew);
            $key = array_search(Constant::DEFAULT_LOCALE, $diff, true);
            if (is_int($key)) {
                unset($diff[$key]);
            }

            foreach ($diff as $locale) {
                $where = ["`locale` = '" . $locale . "' AND `region_id` =" . $regionId];
                $this->resourceConnection->getConnection()
                    ->delete(
                        $this->resourceConnection->getTableName(Constant::DIRECTORY_COUNTRY_REGION_NAME),
                        $where
                    );
            }
        } catch (Exception $e) {
            return;
        }
    }

    /**
     * @param $regionId
     * @return void
     * @throws CouldNotSaveException
     */
    public function addDefaultNameAndLocale($regionId): void
    {
        try {
            if (!$this->isExistDefaultName($regionId)) {
                $model = $this->modelFactory->create();
                $this->resource->load($model, $regionId);
                $regionNameData['name'] = $model->getDefaultName();
                $regionNameData['region_id'] = $regionId;
                $regionNameData['locale'] = Constant::DEFAULT_LOCALE;
                $this->setRegionName($regionNameData);
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * @param $regionId
     * @return int
     */
    protected function isExistDefaultName($regionId) : int
    {
        try {
            $connect = $this->resourceConnection->getConnection();
            $select = $connect->select()
                ->from($connect->getTableName(Constant::DIRECTORY_COUNTRY_REGION_NAME))
                ->where(RegionInterface::REGION_ID.' = ?', $regionId)
                ->where(RegionInterface::LOCALE . ' = ?', Constant::DEFAULT_LOCALE);
            $dataSelected = $connect->fetchAll($select);
        return count($dataSelected);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return 0;
        }

    }

    /**
     * @return void
     * @throws AlreadyExistsException
     */
    public function setRegionName($regionName)
    {
        try {
            $this->resourceConnection->getConnection()
                ->insertOnDuplicate(
                    $this->resourceConnection->getTableName(Constant::DIRECTORY_COUNTRY_REGION_NAME),
                    $regionName,
                    ['name']
                );
        } catch (Exception $e) {
            throw new CouldNotSaveException(__('Could not save RegionName.'));
        }
    }
}
