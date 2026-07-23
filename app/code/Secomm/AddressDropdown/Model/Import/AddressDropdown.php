<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Import;

use Exception;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\ImportExport\Helper\Data as ImportHelper;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\Entity\AbstractEntity;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\ImportExport\Model\ResourceModel\Helper;
use Magento\ImportExport\Model\ResourceModel\Import\Data;
use Secomm\AddressDropdown\Api\Data\CityInterfaceFactory;
use Secomm\AddressDropdown\Api\Data\RegionInterfaceFactory;
use Secomm\AddressDropdown\Api\Data\SubCityInterfaceFactory;
use Secomm\AddressDropdown\Model\Import\Validator\ValidatorInterface;

class AddressDropdown extends AbstractEntity
{
    const ENTITY_CODE = 'address_dropdown';
    const ENTITY_ID_COLUMN = 'country_id';
    const REGION_CODE = 'code_region';
    const LOCALE = 'locale';
    const REGION_DEFAULT_NAME = 'region_default_name';
    const REGION_NAME = 'region_name';
    const CITY_DEFAULT_NAME = 'city_default_name';
    const CITY_NAME = 'city_name';
    const SUB_CITY_DEFAULT_NAME = 'sub_city_default_name';
    const SUB_CITY_NAME = 'sub_city_name';

    /**
     * If we should check column names
     */
    protected $needColumnCheck = true;

    /**
     * Need to log in import history
     */
    protected $logInHistory = true;

    /**
     * Permanent entity columns.
     *
     * @var string[]
     */
    protected $_permanentAttributes = [
        self::LOCALE,
        self::ENTITY_ID_COLUMN,
        self::REGION_CODE,
        self::REGION_DEFAULT_NAME,
        self::REGION_NAME,
        self::CITY_DEFAULT_NAME,
        self::CITY_NAME,
        self::SUB_CITY_DEFAULT_NAME,
        self::SUB_CITY_NAME
    ];

    /**
     * Valid column names
     */
    protected $validColumnNames = [
        self::LOCALE,
        self::ENTITY_ID_COLUMN,
        self::REGION_CODE,
        self::REGION_DEFAULT_NAME,
        self::REGION_NAME,
        self::CITY_DEFAULT_NAME,
        self::CITY_NAME,
        self::SUB_CITY_DEFAULT_NAME,
        self::SUB_CITY_NAME
    ];

    /**
     * @var SaveAddressImport
     */
    protected SaveAddressImport $saveAddressImport;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resource;

    /**
     * @var AdapterInterface
     */
    private AdapterInterface $connection;

    /**
     * @var \Secomm\AddressDropdown\Model\Import\DeleteAddressImport
     */
    private \Secomm\AddressDropdown\Model\Import\DeleteAddressImport $deleteAddressImport;

    /**
     * @var ValidatorInterface
     */
    private ValidatorInterface $validatorInterface;

    /**
     * @param JsonHelper $jsonHelper
     * @param ImportHelper $importExportData
     * @param Data $importData
     * @param ResourceConnection $resource
     * @param Helper $resourceHelper
     * @param ProcessingErrorAggregatorInterface $errorAggregator
     * @param SaveAddressImport $saveAddressImport
     * @param DeleteAddressImport $deleteAddressImport
     * @param ValidatorInterface $validatorInterface
     */
    public function __construct(
        JsonHelper                         $jsonHelper,
        ImportHelper                       $importExportData,
        Data                               $importData,
        ResourceConnection                 $resource,
        Helper                             $resourceHelper,
        ProcessingErrorAggregatorInterface $errorAggregator,
        SaveAddressImport                  $saveAddressImport,
        DeleteAddressImport                $deleteAddressImport,
        ValidatorInterface                 $validatorInterface
    )
    {
        $this->jsonHelper = $jsonHelper;
        $this->_importExportData = $importExportData;
        $this->_resourceHelper = $resourceHelper;
        $this->_dataSourceModel = $importData;
        $this->resource = $resource;
        $this->connection = $resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $this->errorAggregator = $errorAggregator;
        $this->saveAddressImport = $saveAddressImport;
        $this->deleteAddressImport = $deleteAddressImport;
        $this->validatorInterface = $validatorInterface;
        $this->initMessageTemplates();
    }

    /**
     * Init Error Messages
     */
    private function initMessageTemplates()
    {
        foreach ($this->errorMessageTemplates as $errorCode => $message) {
            $this->getErrorAggregator()->addErrorMessageTemplate($errorCode, $message);
        }
    }

    /**
     * Get available columns
     *
     * @return array
     */
    private function getAvailableColumns(): array
    {
        return $this->validColumnNames;
    }

    /**
     * Entity type code getter.
     *
     * @return string
     */
    public function getEntityTypeCode(): string
    {
        return static::ENTITY_CODE;
    }

    /**
     * Import data
     *
     * @return bool
     *
     * @throws Exception
     */
    protected function _importData(): bool
    {
        switch ($this->getBehavior()) {
            case Import::BEHAVIOR_DELETE:
                $this->deleteEntity();
                break;
            case Import::BEHAVIOR_APPEND:
            case Import::BEHAVIOR_REPLACE:
                $this->saveAndReplaceEntity();
                break;
            default:
                break;
        }

        return true;
    }

    /**
     * Delete entities
     *
     * @return bool
     */
    private function deleteEntity(): bool
    {
        $rows = [];
        $rowsData = [];
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            foreach ($bunch as $rowNum => $rowData) {

                if (!$this->getErrorAggregator()->isRowInvalid($rowNum)) {
                    $rowId = $rowData[static::ENTITY_ID_COLUMN];
                    $rowRegionCode = $rowData[static::REGION_CODE];
                    $rows[] = $rowId;
                    $rowsData[$rowId][] = $rowRegionCode;
                }

                if ($this->getErrorAggregator()->hasToBeTerminated()) {
                    $this->getErrorAggregator()->addRowToSkip($rowNum);
                }
            }
        }

        if ($rows) {
            return $this->deleteEntityFinish(array_unique($rows), $rowsData);
        }

        return false;
    }

    /**
     * Row validation
     *
     * @param array $rowData
     * @param int $rowNum
     *
     * @return bool
     */
    public function validateRow(array $rowData, $rowNum): bool
    {
        $result = $this->validatorInterface->validate($rowData, $rowNum);
        if ($result->isValid()) {
            return true;
        }

        foreach ($result->getErrors() as $error) {
            $this->addRowError($error, $rowNum);
        }

        return false;
    }

    /**
     * Delete entities
     *
     * @param array $entityIds
     * @param $rowsData
     * @return bool
     */
    private function deleteEntityFinish(array $entityIds, $rowsData): bool
    {
        if ($entityIds) {
            try {
                foreach ($entityIds as $entityId) {
                    foreach ($rowsData[$entityId] as $rowItem) {
                        if ($this->deleteAddressImport->execute($entityId, $rowItem)) {
                            $this->countItemsDeleted++;
                        }
                    }
                }
                return true;
            } catch (Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Save and replace entities
     *
     * @return bool
     */
    private function saveAndReplaceEntity(): bool
    {
        $behavior = $this->getBehavior();
        $rows = [];
        $rowsData = [];
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $entityList = [];

            foreach ($bunch as $rowNum => $row) {
                if (!$this->validateRow($row, $rowNum)) {
                    continue;
                }

                if ($this->getErrorAggregator()->hasToBeTerminated()) {
                    $this->getErrorAggregator()->addRowToSkip($rowNum);

                    continue;
                }

                $rowId = $row[static::ENTITY_ID_COLUMN];
                $rowRegionCode = $row[static::REGION_CODE];
                $rows[] = $rowId;
                $rowsData[$rowId][] = $rowRegionCode;
                $columnValues = [];

                foreach ($this->getAvailableColumns() as $columnKey) {
                    $columnValues[$columnKey] = $row[$columnKey];
                }

                $entityList[$rowId][] = $columnValues;
            }

            if (Import::BEHAVIOR_REPLACE === $behavior) {
                if ($rows && $this->deleteEntityFinish(array_unique($rows), $rowsData)) {
                    $this->saveEntityFinish($entityList);
                }
            } elseif (Import::BEHAVIOR_APPEND === $behavior) {
                $this->saveEntityFinish($entityList);
            }
        }
        return false;
    }

    /**
     * Save entities
     *
     * @param array $entityData
     *
     * @return bool
     */
    private function saveEntityFinish(array $entityData): bool
    {
        if ($entityData) {
            $rows = [];

            foreach ($entityData as $entityRows) {
                foreach ($entityRows as $row) {
                    $rows[] = $row;
                }
            }

            if ($rows) {
                foreach ($rows as $row) {
                    $this->saveAddressImport->execute($row);
                }

                $this->countItemsUpdated += $this->saveAddressImport->getCountItemsUpdated();
                $this->countItemsCreated += $this->saveAddressImport->getCountItemsCreated();
                return true;
            }
        }
        return false;
    }
}