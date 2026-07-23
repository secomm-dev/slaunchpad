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

namespace Mageplaza\AbandonedCart\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

/**
 * Class OrderSuccessCount
 *
 * @package Mageplaza\AbandonedCart\Model
 */
class OrderSuccessCount
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * OrderSuccessCount constructor.
     *
     * @param ResourceConnection $resourceConnection
     * @param CollectionFactory $orderCollectionFactory
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        CollectionFactory $orderCollectionFactory
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * Get total success order
     *
     * @param $fromDate
     * @param $toDate
     * @param $storeId
     * @return int
     */
    public function getSuccessOrderCount($fromDate = null, $toDate = null, $storeId = null)
    {
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $select = $connection->select()
            ->from($orderTable, 'COUNT(*)')
            ->where('status = ?', 'pending');

        if ($fromDate && $toDate) {
            $select->where('created_at >= ?', $fromDate)
                ->where('created_at <= ?', $toDate);
        }
        if ($storeId) {
            $select->where('store_id = ?', $storeId);
        }

        $count = $connection->fetchOne($select);

        return $count;
    }
}
