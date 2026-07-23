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
use Magento\Catalog\Model\Product\Type;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\Timezone\Validator;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Reports\Model\FlagFactory;
use Magento\Reports\Model\ResourceModel\Report\AbstractReport;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\IteratorFactory;
use Psr\Log\LoggerInterface;
use Zend_Db_Expr;

/**
 * Class ProductReport
 * @package Mageplaza\AbandonedCart\Model\ResourceModel
 */
class ProductReport extends AbstractReport
{
    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * ProductReport constructor.
     *
     * @param Context $context
     * @param LoggerInterface $logger
     * @param TimezoneInterface $localeDate
     * @param FlagFactory $reportsFlagFactory
     * @param Validator $timezoneValidator
     * @param DateTime $dateTime
     * @param IteratorFactory $iteratorFactory
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
        $connectionName = null
    ) {
        $this->iteratorFactory = $iteratorFactory;

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
        $this->_init('mageplaza_abandonedcart_product_reports_index', 'id');
    }

    /**
     * @param null $from
     * @param null $to
     *
     * @return $this
     * @throws Exception
     */
    public function aggregate($from = null, $to = null)
    {
        $connection = $this->getConnection();
        //$this->getConnection()->beginTransaction();

        try {
            if ($from !== null || $to !== null) {
                $subSelect = $this->_getTableDateRangeSelect(
                    $this->getTable('quote'),
                    'created_at',
                    'updated_at',
                    $from,
                    $to
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

            $select->group([$periodExpr, 'source_table.store_id', 'quote_item.product_id']);

            $columns = [
                'period'            => $periodExpr,
                'store_id'          => 'source_table.store_id',
                'product_id'        => 'quote_item.product_id',
                'sku'               => 'quote_item.sku',
                'product_name'      => new Zend_Db_Expr('MIN(quote_item.name)'),
                'price'             => new Zend_Db_Expr(
                    'IF(
                    quote_item.parent_item_id IS NULL AND quote_item.product_type = \'simple\',
                    quote_item.base_price,
                    IF(
                    quote_item_parent.product_type = \'bundle\',
                    quote_item.base_price,
                    quote_item_parent.base_price
                    )
                    )'
                ),
                'abandoned_time'    => new Zend_Db_Expr(
                    'COUNT(quote_item.product_id)'
                ),
                'qty'               => new Zend_Db_Expr(
                    'SUM(
                    IF(
                    quote_item.parent_item_id IS NULL,
                    quote_item.qty,
                    IF(
                    quote_item_parent.product_type = \'bundle\',
                    quote_item.qty*quote_item_parent.qty,
                    quote_item_parent.qty
                    )
                    )
                    )'
                ),
                'abandoned_revenue' => new Zend_Db_Expr(
                    'SUM(
                    IF(
                    quote_item.parent_item_id IS NULL,
                    quote_item.base_row_total,
                    IF(
                    quote_item_parent.product_type = \'bundle\',
                    quote_item.base_row_total,
                    quote_item_parent.base_row_total
                    )
                    )
                    )'
                ),

                'customer_group_id' => 'source_table.customer_group_id',
            ];

            $select->from(
                ['source_table' => $this->getTable('quote')],
                $columns
            )->where(
                'source_table.is_active = 1'
            )->joinInner(
                ['quote_item' => $this->getTable('quote_item')],
                'quote_item.quote_id = source_table.entity_id',
                []
            )->joinLeft(
                ['quote_item_parent' => $this->getTable('quote_item')],
                'quote_item.parent_item_id = quote_item_parent.item_id',
                []
            )->where(
                ' quote_item.product_type NOT IN(?)',
                [
                    Type::TYPE_BUNDLE       => Type::TYPE_BUNDLE,
                    Grouped::TYPE_CODE      => Grouped::TYPE_CODE,
                    Configurable::TYPE_CODE => Configurable::TYPE_CODE
                ]
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
     * @param $args
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
