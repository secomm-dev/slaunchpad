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
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ExtraFee\Model\ResourceModel\Order\Grid;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\ResourceModel\Order;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OriginalCollection;
use Mageplaza\ExtraFee\Helper\Data;
use Psr\Log\LoggerInterface as Logger;

/**
 * Class Collection
 * @package Mageplaza\ExtraFee\Model\ResourceModel\Order\Grid
 */
class Collection extends OriginalCollection
{
    /**
     * @var Data
     */
    protected $helperData;

    /**
     * Collection constructor.
     *
     * @param Data $helperData
     * @param EntityFactory $entityFactory
     * @param Logger $logger
     * @param FetchStrategy $fetchStrategy
     * @param EventManager $eventManager
     * @param string $mainTable
     * @param string $resourceModel
     * @param TimezoneInterface|null $timeZone
     */
    public function __construct(
        Data $helperData,
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategy $fetchStrategy,
        EventManager $eventManager,
        $mainTable = 'sales_order_grid',
        $resourceModel = Order::class,
        ?TimezoneInterface $timeZone = null
    ) {
        $this->helperData = $helperData;

        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel
        );
    }

    /**
     * Get Mageplaza Extra Fee
     */
    protected function _renderFiltersBefore()
    {
        if ($this->helperData->isEnabled()) {
            $joinTable = $this->getTable('sales_order');
            $this->getSelect()->joinLeft(
                ['sales_order_table' => $joinTable],
                'main_table.entity_id = sales_order_table.entity_id',
                ['mp_extra_fee']
            );
        }

        parent::_renderFiltersBefore();
    }
}
