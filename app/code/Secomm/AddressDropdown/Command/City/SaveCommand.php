<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Command\City;

use Exception;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Api\Data\CityNameInterface;
use Secomm\AddressDropdown\Api\Data\CityNameInterfaceFactory;
use Secomm\AddressDropdown\Command\City\SaveValidator;
use Secomm\AddressDropdown\Model\CityModel;
use Secomm\AddressDropdown\Model\CityModelFactory;
use Secomm\AddressDropdown\Model\Constant;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityCollection;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityCollectionFactory;
use Secomm\AddressDropdown\Model\ResourceModel\CityNameModel\CityNameCollection;
use Secomm\AddressDropdown\Model\ResourceModel\CityNameModel\CityNameCollectionFactory;
use Secomm\AddressDropdown\Model\ResourceModel\CityNameResource;
use Secomm\AddressDropdown\Model\ResourceModel\CityResource;

/**
 * Save City Command.
 */
class SaveCommand
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    /**
     * @var CityModelFactory
     */
    private CityModelFactory $modelFactory;
    /**
     * @var CityResource
     */
    private CityResource $resource;
    /**
     * @var CityCollectionFactory
     */
    private CityCollectionFactory $cityCollectionFactory;
    /**
     * @var CityNameInterfaceFactory
     */
    private $cityNameInterfaceFactory;
    /**
     * @var CityNameResource
     */
    private CityNameResource $cityNameResource;

    /**
     * @var CityNameCollectionFactory
     */
    private CityNameCollectionFactory $cityNameCollectionFactory;

    /**
     * TASK-9EX975 Slice B: hierarchy validation (same-region parent, self/cycle,
     * MAX_DEPTH, duplicate code) shared with the import rules.
     */
    private SaveValidator $saveValidator;

    /**
     * @param LoggerInterface $logger
     * @param CityModelFactory $modelFactory
     * @param CityResource $resource
     * @param CityCollectionFactory $cityCollectionFactory
     * @param CityNameInterfaceFactory $cityNameInterfacefactory
     * @param CityNameResource $cityNameResource
     * @param CityNameCollectionFactory $cityNameCollectionFactory
     * @param ResourceConnection $resourceConnection
     * @param SaveValidator $saveValidator
     */
    public function __construct(
        LoggerInterface           $logger,
        CityModelFactory          $modelFactory,
        CityResource              $resource,
        CityCollectionFactory     $cityCollectionFactory,
        CityNameInterfaceFactory  $cityNameInterfaceFactory,
        CityNameResource          $cityNameResource,
        CityNameCollectionFactory $cityNameCollectionFactory,
        ResourceConnection        $resourceConnection,
        SaveValidator             $saveValidator
    )
    {
        $this->logger = $logger;
        $this->modelFactory = $modelFactory;
        $this->resource = $resource;
        $this->cityCollectionFactory = $cityCollectionFactory;
        $this->cityNameInterfaceFactory = $cityNameInterfaceFactory;
        $this->cityNameResource = $cityNameResource;
        $this->cityNameCollectionFactory = $cityNameCollectionFactory;
        $this->resourceConnection = $resourceConnection;
        $this->saveValidator = $saveValidator;
    }

    /**
     * Save City.
     *
     * @param CityInterface $city
     * @return int
     * @throws CouldNotSaveException
     * @throws InvalidArgumentException
     * @throws LocalizedException validation failures rethrown verbatim (friendly message)
     */
    public function execute(CityInterface $city): int
    {
        try {
            $warnings = $this->saveValidator->validate($city);
            foreach ($warnings as $warning) {
                $this->logger->warning('City save warning: ' . $warning, [
                    'city_id' => $city->getCityId(),
                    'region_id' => $city->getRegionId(),
                ]);
            }

            /** @var CityModel $model */
            $model = $this->modelFactory->create();
            // TASK-9EX975 Slice B: the form's empty-caption options arrive as '' — normalise
            // to NULL so the FK/composite-unique semantics stay "no parent / no code".
            $modelData = $city->getData();
            foreach ([CityInterface::PARENT_CITY_ID, CityInterface::CODE] as $optionalField) {
                if (array_key_exists($optionalField, $modelData)
                    && is_string($modelData[$optionalField])
                    && trim($modelData[$optionalField]) === ''
                ) {
                    $modelData[$optionalField] = null;
                }
            }
            $model->addData($modelData);
            $model->setHasDataChanges(true);


            if (!$model->getData(CityInterface::CITY_ID)) {
                $model->isObjectNew(true);
            }
            $this->resource->save($model);

            // Save City Name from Form Admin
            $cityNames = $model->getCityName();
            if (is_object($cityNames) || is_array($cityNames)) {
                $this->checkAndDeleteCityNames($cityNames, (int)$model->getData(CityInterface::CITY_ID));
                foreach ($cityNames as $cityName) {
                    $cityName['city_id'] = (int)$model->getData(CityInterface::CITY_ID);
                    unset($cityName['record_id']);
                    unset($cityName['initialize']);
                    $this->setCityName($cityName);
                }
            }

            // Save City Name From File Import
            if (is_string($cityNames) && $cityNames !== '') {
                $cityNameData['name'] = $cityNames;
                $cityNameData['city_id'] = (int)$model->getData(CityInterface::CITY_ID);
                $cityNameData['locale'] = $model->getData(CityNameInterface::LOCALE);
                $this->setCityName($cityNameData);
            }

            // Remove all City Names locale
            if (is_null($cityNames) && !is_string($cityNames)) {
                $this->checkAndDeleteCityNames([], (int)$model->getData(CityInterface::CITY_ID));
            }

            // add Default Name and Locale "en_US"
            $this->addDefaultNameAndLocale((int)$model->getData(CityInterface::CITY_ID));
        } catch (LocalizedException $exception) {
            // Validation failures carry their friendly message to the admin — rethrow verbatim.
            throw $exception;
        } catch (Exception $exception) {
            $this->logger->error(
                __('Could not save City. Original message: {message}'),
                [
                    'message' => $exception->getMessage(),
                    'exception' => $exception
                ]
            );
            throw new CouldNotSaveException(__('Could not save City.'));
        }

        return (int)$model->getData(CityInterface::CITY_ID);
    }

    /**
     * @param array $cityNames
     * @param $cityId
     * @return void
     */
    protected function checkAndDeleteCityNames(array $cityNames, $cityId): void
    {
        try {
            $allCityNameExist = $this->cityNameCollectionFactory->create()->addFieldToFilter('city_id', $cityId);
            $listLocaleExist = array_column($allCityNameExist->getData(), 'locale');
            $listLocaleNew = array_column($cityNames, 'locale');
            $diff = array_diff($listLocaleExist, $listLocaleNew);
            $key = array_search(Constant::DEFAULT_LOCALE, $diff, true);
            if (is_int($key)) {
                unset($diff[$key]);
            }

            foreach ($diff as $locale) {
                $where = ["`locale` = '" . $locale . "' AND `city_id` =" . $cityId];
                $this->resourceConnection->getConnection()
                    ->delete(
                        $this->cityNameResource->getMainTable(),
                        $where
                    );
            }
        } catch (Exception $e) {
            return;
        }
    }

    /**
     * @param $cityId
     * @return void
     * @throws CouldNotSaveException
     */
    public function addDefaultNameAndLocale($cityId): void
    {
        try {
            /** @var CityNameCollection $cityNameCollection */
            $cityNameCollection = $this->cityNameCollectionFactory->create();
            $cityNameCollection->addFieldToFilter(CityNameInterface::LOCALE, Constant::DEFAULT_LOCALE)
                ->addFieldToFilter(CityNameInterface::CITY_ID, $cityId);
            if (!$cityNameCollection->count()) {
                $model = $this->modelFactory->create();
                $this->resource->load($model, $cityId);
                $cityNameData['name'] = $model->getDefaultName();
                $cityNameData['city_id'] = $cityId;
                $cityNameData['locale'] = Constant::DEFAULT_LOCALE;
                $this->setCityName($cityNameData);
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * @param mixed $cityName
     * @return void
     * @throws CouldNotSaveException
     */
    private function setCityName(mixed $cityName): void
    {
        try {
            $this->resourceConnection->getConnection()
                ->insertOnDuplicate(
                    $this->resourceConnection->getTableName($this->cityNameResource->getMainTable()),
                    $cityName,
                    [CityNameInterface::NAME]
                );
        } catch (Exception $e) {
            throw new CouldNotSaveException(__('Could not save CityName.'));
        }
    }
}
