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
 * @package     Mageplaza_AbandonedCart
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\AbandonedCart\Model\ResourceModel;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime\Timezone\Validator;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Reports\Model\FlagFactory;
use Magento\Reports\Model\ResourceModel\Report\AbstractReport;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Model\ResourceModel\IteratorFactory;
use Mageplaza\AbandonedCart\Helper\Data;
use Psr\Log\LoggerInterface;
use Zend_Db_Expr;

/**
 * Class AbandonedCarts
 * @package Mageplaza\AbandonedCart\Model\ResourceModel
 */
class AbandonedCarts extends AbstractReport
{
    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var Data
     */
    private $helperData;

    /**
     * AbandonedCarts constructor.
     *
     * @param Context $context
     * @param LoggerInterface $logger
     * @param TimezoneInterface $localeDate
     * @param FlagFactory $reportsFlagFactory
     * @param Validator $timezoneValidator
     * @param DateTime $dateTime
     * @param IteratorFactory $iteratorFactory
     * @param Data $helperData
     * @param null $connectionName
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        TimezoneInterface $localeDate,
        FlagFactory $reportsFlagFactory,
        Validator $timezoneValidator,
        DateTime $dateTime,
        IteratorFactory $iteratorFactory,
        Data $helperData,
        $connectionName = null
    ) {
        $this->iteratorFactory = $iteratorFactory;
        $this->helperData      = $helperData;
        parent::__construct(
            $context,
            $logger,
            $localeDate,
            $reportsFlagFactory,
            $timezoneValidator,
            $dateTime,
            $connectionName
        );
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('mageplaza_abandonedcart_reports_index', 'id');
    }

    /**
     * @param DateTime|null $from
     * @param null $to
     *
     * @return $this
     * @throws Exception
     */
    public function aggregate($from = null, $to = null)
    {
        $connection = $this->getConnection();

        $subLogsTable = $connection->select();
        $columns      = [
            'quote_id'         => new Zend_Db_Expr('quote_id'),
            'total_email_sent' => new Zend_Db_Expr('COUNT(quote_id)'),
            'status'           => new Zend_Db_Expr('status'),
            'recovery'         => new Zend_Db_Expr('recovery'),
            'updated_at'       => new Zend_Db_Expr('updated_at')
        ];

        $subLogsTable->from(
            $this->getTable('mageplaza_abandonedcart_logs')
        )->group(
            ['quote_id']
        )->reset(
            \Magento\Framework\DB\Select::COLUMNS
        )->columns($columns);

        try {
            if ($from !== null || $to !== null) {
                $subSelect = $connection->select()->from(
                    ['rt' => $this->getTable('quote')],
                    $connection->getDatePartSql(
                        $this->getStoreTZOffsetQuery(
                            ['rt' => 'quote'],
                            'rt.created_at',
                            $from,
                            $to
                        )
                    )
                )->distinct(
                    true
                )->joinLeft(
                    ['logs' => $subLogsTable],
                    'rt.entity_id = logs.quote_id',
                    []
                );
            } else {
                $subSelect = null;
            }

            $this->_clearTableByDateRange($this->getMainTable(), $from, $to, $subSelect);
            // convert dates to current admin timezone
            $periodExpr = $connection->getDatePartSql(
                $this->getStoreTZOffsetQuery(
                    ['source_table' => $this->getTable('quote')],
                    'source_table.created_at',
                    $from,
                    $to
                )
            );
            $select     = $connection->select();

            $select->group([$periodExpr, 'source_table.store_id', 'source_table.customer_group_id']);
            $storeId     = $this->helperData->getStoreFilter() ?: null;
            $timeMeasure = $this->helperData->getRealtimeConfig('time_measure', $storeId);
            $currentTimeMinusMeasure = strtotime("-$timeMeasure minute");
            $columns = [
                'period'                     => $periodExpr,
                'store_id'                   => 'source_table.store_id',
                'number_of_successful_carts' => new Zend_Db_Expr(
                    'COUNT(CASE WHEN source_table.is_active = 0 THEN source_table.is_active END)'
                ),
                'total_abandoned_carts'      => new Zend_Db_Expr(
                    'COUNT(CASE WHEN source_table.items_count > 0 AND  source_table.is_active = 1 AND source_table.updated_at < \'' . date('Y-m-d H:i:s', $currentTimeMinusMeasure) . '\' THEN source_table.is_active END)'
                ),
                'successful_carts_revenue' => new Zend_Db_Expr(
                    'SUM(CASE  WHEN source_table.is_active = 0 AND sales_order.mp_abandoned_return_cart = 1 THEN source_table.base_grand_total END)'
                ),
                'actionable_abandoned_carts' => new Zend_Db_Expr(
                    'COUNT(CASE WHEN source_table.items_count > 0 AND source_table.is_active = 1 AND source_table.customer_email IS NOT NULL
                     THEN source_table.is_active END)'
                ),
                'total_abandoned_revenue'    => new Zend_Db_Expr(
                    'SUM(CASE WHEN source_table.items_count > 0 AND source_table.is_active = 1 AND source_table.updated_at < \'' . date('Y-m-d H:i:s', $currentTimeMinusMeasure) . '\' THEN source_table.base_grand_total END)'
                ),
                'total_email_abandoned_sent' => new Zend_Db_Expr(
                    'SUM(CASE WHEN source_table.entity_id = lt.quote_id AND lt.status != 0
                    THEN lt.total_email_sent END)'
                ),
                'recapturable_revenue'       => new Zend_Db_Expr(
                    'SUM(CASE WHEN source_table.is_active = 1 AND source_table.entity_id = lt.quote_id
                     AND lt.status != 0 THEN source_table.base_grand_total END)'
                ),
                'recaptured_revenue'         => new Zend_Db_Expr(
                    'SUM(CASE WHEN source_table.is_active = 0 AND source_table.entity_id = lt.quote_id
                    AND lt.recovery = 1 THEN source_table.base_grand_total END)'
                ),
                'total_cart_checkout_sent'   => new Zend_Db_Expr(
                    'COUNT(CASE WHEN source_table.entity_id = lt.quote_id AND source_table.is_active = 0
                    AND lt.status != 0 THEN source_table.base_grand_total END)'
                ),

                'customer_group_id' => 'MAX(source_table.customer_group_id)',
            ];

            $select->from(
                ['source_table' => $this->getTable('quote')],
                $columns
            )->joinLeft(
                ['lt' => $subLogsTable],
                'source_table.entity_id = lt.quote_id',
                []
            )->joinLeft(
                ['sales_order' => $this->getTable('sales_order')],
                'source_table.entity_id = sales_order.quote_id',
                []
            );

            if ($subSelect !== null) {
                $select->having($this->_makeConditionFromDateRangeSelect($subSelect, 'period'));
            }

            $select->useStraightJoin();

            $iterator = $this->iteratorFactory->create();
            $iterator->walk($select, [[$this, 'insertFromSelect']]);

        } catch (Exception $e) {
            throw $e;
        }

        return $this;
    }

    /**
     * @param array $args
     *
     * @return void
     */
    public function insertFromSelect($args)
    {
        $columns = $args['row'];
        try {
            $this->getConnection()->insert($this->getMainTable(), $columns);
        } catch (LocalizedException $e) {
            $this->_logger->critical($e->getMessage());
        }
    }
}
