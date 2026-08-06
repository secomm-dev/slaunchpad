<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\Ahamove\Model\ResourceModel\Carrier\ShippingMethod;

use Magento\Framework\Filesystem;
use Secomm\Ahamove\Model\ResourceModel\Carrier\ShippingMethod\Express\Import;
use Secomm\Ahamove\Model\ResourceModel\Carrier\ShippingMethod\Express\RateQuery;
use Secomm\Ahamove\Model\ResourceModel\Carrier\ShippingMethod\Express\RateQueryFactory;

class Express extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Import table rates website ID
     *
     * @var int
     */
    protected $importWebsiteId = 0;

    /**
     * Errors in import process
     *
     * @var array
     */
    protected $importErrors = [];

    /**
     * Count of imported table rates
     *
     * @var int
     */
    protected $importedRows = 0;

    /**
     * Array of unique table rate keys to protect from duplicates
     *
     * @var array
     */
    protected $importUniqueHash = [];

    /**
     * Array of countries keyed by iso2 code
     *
     * @var array
     */
    protected $importIso2Countries;

    /**
     * Array of countries keyed by iso3 code
     *
     * @var array
     */
    protected $importIso3Countries;

    /**
     * Associative array of countries and regions
     * [country_id][region_code] = region_id
     *
     * @var array
     */
    protected $importRegions;

    /**
     * Import Table Rate condition name
     *
     * @var string
     */
    protected $importConditionName;

    /**
     * Array of condition full names
     *
     * @var array
     */
    protected $conditionFullNames = [];

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     * @since 100.1.0
     */
    protected $coreConfig;

    /**
     * @var \Psr\Log\LoggerInterface
     * @since 100.1.0
     */
    protected $logger;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     * @since 100.1.0
     */
    protected $storeManager;

    /**
     * @var \Secomm\Ahamove\Model\ResourceModel\Carrier\ShippingMethod\Standard
     * @since 100.1.0
     */
    protected $carrierTablerate;

    /**
     * Filesystem instance
     *
     * @var \Magento\Framework\Filesystem
     * @since 100.1.0
     */
    protected $filesystem;

    /**
     * @var Import
     */
    private $import;

    /**
     * @var RateQueryFactory
     */
    private $rateQueryFactory;

    /**
     * Tablerate constructor.
     * @param \Magento\Framework\Model\ResourceModel\Db\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $coreConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Secomm\Ahamove\Model\Carrier\ShippingMethod\Standard $carrierTablerate
     * @param Filesystem $filesystem
     * @param RateQueryFactory $rateQueryFactory
     * @param Import $import
     * @param null $connectionName
     */
    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $coreConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Secomm\Ahamove\Model\Carrier\ShippingMethod\Standard $carrierTablerate,
        \Magento\Framework\Filesystem $filesystem,
        Import $import,
        RateQueryFactory $rateQueryFactory,
        $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
        $this->coreConfig = $coreConfig;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->carrierTablerate = $carrierTablerate;
        $this->filesystem = $filesystem;
        $this->import = $import;
        $this->rateQueryFactory = $rateQueryFactory;
    }

    /**
     * Define main table and id field name
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('ahamove_express', 'pk');
    }

    /**
     * Return table rate array or false by rate request
     *
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return array|bool
     */
    public function getRate(\Magento\Quote\Model\Quote\Address\RateRequest $request)
    {
        $connection = $this->getConnection();

        $select = $connection->select()->from($this->getMainTable());
        /** @var RateQuery $rateQuery */
        $rateQuery = $this->rateQueryFactory->create(['request' => $request]);

        $rateQuery->prepareSelect($select);
        $bindings = $rateQuery->getBindings();

        $result = $connection->fetchRow($select, $bindings);
        // Normalize destination zip code
        if ($result && $result['dest_zip'] == '*') {
            $result['dest_zip'] = '';
        }

        return $result;
    }

    /**
     * @param array $condition
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function deleteByCondition(array $condition)
    {
        $connection = $this->getConnection();
        $connection->beginTransaction();
        $connection->delete($this->getMainTable(), $condition);
        $connection->commit();
        return $this;
    }

    /**
     * @param array $fields
     * @param array $values
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return void
     */
    private function importData(array $fields, array $values)
    {
        $connection = $this->getConnection();
        $connection->beginTransaction();

        try {
            if (count($fields) && count($values)) {
                $this->getConnection()->insertArray($this->getMainTable(), $fields, $values);
                $this->importedRows += count($values);
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $connection->rollBack();
            throw new \Magento\Framework\Exception\LocalizedException(__('Unable to import data'), $e);
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logger->critical($e);
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Something went wrong while importing ahamove express.')
            );
        }
        $connection->commit();
    }

    /**
     * Upload ahamove express file and import data from it
     *
     * @param \Magento\Framework\DataObject $object
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return \Secomm\Ahamove\Model\ResourceModel\Carrier\ShippingMethod\Express
     * @todo: this method should be refactored as soon as updated design will be provided
     * @see https://wiki.corp.x.com/display/MCOMS/Magento+Filesystem+Decisions
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function uploadAndImport(\Magento\Framework\DataObject $object)
    {
        /**
         * @var \Magento\Framework\App\Config\Value $object
         */
        if (empty($_FILES['groups']['tmp_name']['ahamove']['groups']['ahamove_shipping_method']['groups']['ahamove_express']['fields']['import']['value'])) {
            return $this;
        }
        $filePath = $_FILES['groups']['tmp_name']['ahamove']['groups']['ahamove_shipping_method']['groups']['ahamove_express']['fields']['import']['value'];

        $websiteId = $this->storeManager->getWebsite($object->getScopeId())->getId();
        $conditionName = $this->getConditionName($object);

        $file = $this->getCsvFile($filePath);
        try {
            // delete old data by website and condition name
            $condition = [
                'website_id = ?' => $websiteId,
                'condition_name = ?' => $conditionName,
            ];
            $this->deleteByCondition($condition);

            $columns = $this->import->getColumns();
            $conditionFullName = $this->_getConditionFullName($conditionName);
            foreach ($this->import->getData($file, $websiteId, $conditionName, $conditionFullName) as $bunch) {
                $this->importData($columns, $bunch);
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Something went wrong while importing ahamove express.')
            );
        } finally {
            $file->close();
        }

        if ($this->import->hasErrors()) {
            $error = __(
                'We couldn\'t import this file because of these errors: %1',
                implode(" \n", $this->import->getErrors())
            );
            throw new \Magento\Framework\Exception\LocalizedException($error);
        }
    }

    /**
     * @param \Magento\Framework\DataObject $object
     * @return mixed|string
     * @since 100.1.0
     */
    public function getConditionName(\Magento\Framework\DataObject $object)
    {
        if ($object->getData('groups/ahamove/groups/ahamove_shipping_method/groups/ahamove_express/fields/condition_name/inherit') == '1') {
            $conditionName = (string)$this->coreConfig->getValue('carriers/ahamove_express/condition_name', 'default');
        } else {
            $conditionName = $object->getData('groups/ahamove/groups/ahamove_shipping_method/groups/ahamove_express/fields/condition_name/value');
        }
        return $conditionName;
    }

    /**
     * @param string $filePath
     * @return \Magento\Framework\Filesystem\File\ReadInterface
     */
    private function getCsvFile($filePath)
    {
        $pathInfo = pathinfo($filePath);
        $dirName = $pathInfo['dirname'] ?? '';
        $fileName = $pathInfo['basename'] ?? '';

        $directoryRead = $this->filesystem->getDirectoryReadByPath($dirName, Filesystem\DriverPool::FILE);

        return $directoryRead->openFile($fileName);
    }

    /**
     * Return import condition full name by condition name code
     *
     * @param string $conditionName
     * @return string
     */
    protected function _getConditionFullName($conditionName)
    {
        if (!isset($this->conditionFullNames[$conditionName])) {
            $name = $this->carrierTablerate->getCode('condition_name_short', $conditionName);
            $this->conditionFullNames[$conditionName] = $name;
        }

        return $this->conditionFullNames[$conditionName];
    }

    /**
     * Save import data batch
     *
     * @param array $data
     * @return \Secomm\Ahamove\Model\ResourceModel\Carrier\ShippingMethod\Express
     */
    protected function _saveImportData(array $data)
    {
        if (!empty($data)) {
            $columns = [
                'website_id',
                'dest_country_id',
                'dest_region_id',
                'dest_zip',
                'condition_name',
                'condition_value',
                'price',
            ];
            $this->getConnection()->insertArray($this->getMainTable(), $columns, $data);
            $this->importedRows += count($data);
        }

        return $this;
    }
}
