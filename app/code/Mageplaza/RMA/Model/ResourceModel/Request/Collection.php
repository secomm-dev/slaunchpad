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
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Model\ResourceModel\Request;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Mageplaza\RMA\Model\Request as RequestModel;
use Mageplaza\RMA\Model\ResourceModel\Request as RequestResource;

/**
 * Class Collection
 * @package Mageplaza\RMA\Model\ResourceModel\Request
 */
class Collection extends AbstractCollection
{
    /**
     * ID Field Name
     *
     * @var string
     */
    protected $_idFieldName = 'request_id';

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'mageplaza_rma_request_collection';

    /**
     * Event object
     *
     * @var string
     */
    protected $_eventObject = 'request_collection';

    /**
     * Define model & resource model
     */
    protected function _construct()
    {
        $this->_init(RequestModel::class, RequestResource::class);
    }

    /**
     * @return $this
     */
    public function addOrderTable()
    {
        $this->getSelect()->joinLeft(
            ['orders' => $this->getTable('sales_order_grid')],
            'main_table.order_id = orders.entity_id',
            ['orders.customer_id']
        );

        return $this;
    }
}
