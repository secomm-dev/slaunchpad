<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_DeliveryTime
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\DeliveryTime\Setup\Patch\Schema;

use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * Class InitializeColumns
 * @package Mageplaza\DeliveryTime\Setup\Patch\Schema
 */
class InitializeColumnsV2 implements SchemaPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * Constructor
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * Apply the patch
     *
     * @return void
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $this->checkAndModifyColumn('sales_order');
        $this->addColumnIfNotExists('quote', 'mp_delivery_information');

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * Check and modify the column for sales_order table
     *
     * @param string $tableName
     *
     * @return void
     */
    private function checkAndModifyColumn($tableName)
    {
        $connection = $this->moduleDataSetup->getConnection();
        $tableName  = $this->moduleDataSetup->getTable($tableName);

        if ($connection->tableColumnExists($tableName, 'osc_delivery_time')) {
            $connection->changeColumn(
                $tableName,
                'osc_delivery_time',
                'mp_delivery_information',
                [
                    'type'     => Table::TYPE_TEXT,
                    'nullable' => true,
                    'visible'  => false,
                    'comment'  => 'Mageplaza Delivery Time'
                ]
            );
        } else {
            $this->addColumnIfNotExists($tableName, 'mp_delivery_information');
        }
    }

    /**
     * Add column if it does not exist
     *
     * @param string $tableName
     * @param string $columnName
     *
     * @return void
     */
    private function addColumnIfNotExists($tableName, $columnName)
    {
        $connection = $this->moduleDataSetup->getConnection();
        $tableName  = $this->moduleDataSetup->getTable($tableName);

        if (!$connection->tableColumnExists($tableName, $columnName)) {
            $connection->addColumn(
                $tableName,
                $columnName,
                [
                    'type'     => Table::TYPE_TEXT,
                    'nullable' => true,
                    'visible'  => false,
                    'comment'  => 'Mageplaza Delivery Time'
                ]
            );
        }
    }

    /**
     * Get the list of dependencies
     *
     * @return array
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * Get aliases
     *
     * @return array
     */
    public function getAliases()
    {
        return [];
    }

    public static function getVersion()
    {
        return '';
    }
}
