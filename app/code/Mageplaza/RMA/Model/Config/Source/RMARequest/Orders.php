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

namespace Mageplaza\RMA\Model\Config\Source\RMARequest;

use Magento\Framework\DB\Select;
use Magento\Framework\Option\ArrayInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\Order\Rule;
use Mageplaza\RMA\Model\Order\RuleFactory;

/**
 * Class Orders
 * @package Mageplaza\RMA\Model\Config\Source\RMARequest
 */
class Orders implements ArrayInterface
{
    /**
     * @var CollectionFactory
     */
    protected $_orderColFact;

    /**
     * @var RuleFactory
     */
    protected $_orderRuleFact;

    /**
     * @var object
     */
    protected $_orderCollection;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * Orders constructor.
     *
     * @param CollectionFactory $orderColFact
     * @param RuleFactory $orderRuleFact
     * @param HelperData $helperData
     */
    public function __construct(
        CollectionFactory $orderColFact,
        RuleFactory $orderRuleFact,
        HelperData $helperData
    ) {
        $this->_orderColFact  = $orderColFact;
        $this->_orderRuleFact = $orderRuleFact;
        $this->_helperData    = $helperData;
    }

    /**
     * @param int $customerId
     *
     * @return array
     */
    public function toOptionArray($customerId = 0)
    {
        $options = [];
        $validatedOrderIds = $this->_getValidateOrderIds($customerId);
        $orderCollection   = $this->_orderColFact->create();
        $orderCollection->addFieldToFilter('entity_id', ['in' => $validatedOrderIds]);
        foreach ($orderCollection as $order) {
            /** @var Order $order */
            $options[] = [
                'value' => $order->getId(),
                'label' => '#' . $order->getIncrementId()
            ];
        }

        return $options;
    }

    /**
     * @param Collection $orderCollection
     *
     * @return array
     */
    public function filterOrderOption($orderCollection)
    {
        $options = [];

        foreach ($orderCollection as $order) {
            /** @var Order $order */
            $orderItems      = $order->getItemsCollection();
            $hasReturnedItem = false;
            foreach ($orderItems as $item) {
                /** @var OrderItem $item */
                if ($item->getParentItem()) {
                    continue;
                }
                if ($this->_helperData->getAvailableQtyToReturn($item) > 0) {
                    $hasReturnedItem = true;
                    break;
                }
            }
            if ($hasReturnedItem) {
                $options[] = [
                    'value'           => $order->getId(),
                    'label'           => '#' . $order->getIncrementId(),
                    'order_increment' => $order->getIncrementId()
                ];
            }
        }

        return $options;
    }

    /**
     * @param int $customerId
     *
     * @return array
     */
    public function toAvailableOptionArray($customerId = 0)
    {
        $validatedOrderIds = $this->_getValidateOrderIds($customerId);
        $orderCollection   = $this->_orderColFact->create();
        $orderCollection->addFieldToFilter('entity_id', ['in' => $validatedOrderIds]);

        return $this->filterOrderOption($orderCollection);
    }

    /**
     * @return array
     */
    public function listID($id)
    {
        $orderCollection = $this->_orderColFact->create();
        $connection      = $orderCollection->getConnection();
        $select          = $connection->select()->from($orderCollection->getMainTable(), $id);

        return $connection->fetchCol($select);
    }

    /**
     * @return array
     */
    public function orderListID()
    {
        return $this->listID('entity_id');
    }

    /**
     * @return array
     */
    public function orderListIncrementId()
    {
        return $this->listID('increment_id');
    }

    /**
     * @param int $customerId
     *
     * @return array
     */
    protected function _getValidateOrderIds($customerId)
    {
        /** @var Collection $orderCol */
        $orderCol   = $this->getOrderCollection($customerId);

        /** @var Rule $rule */
        $rule       = $this->_orderRuleFact->create();
        $conditions = $this->_helperData->getRequestConfig('order_condition');
        $rule->setData('conditions_serialized', $conditions);

        return $rule->getMatchingOrderIds($orderCol);
    }

    /**
     * @param int $customerId
     *
     * @return Collection
     */
    protected function getOrderCollection($customerId)
    {
        if ($this->_orderCollection === null) {
            $orderCollection = $this->_orderColFact->create();
            $orderCollection->getSelect()
                ->reset(Select::COLUMNS)
                ->columns([
                    'entity_id',
                    'base_subtotal',
                    'base_grand_total',
                    'base_total_refunded',
                    'base_total_invoiced',
                    'total_qty_ordered',
                    'weight',
                    'status',
                    'store_id',
                    'customer_group_id',
                    'shipping_method'
                ])
                ->joinLeft(
                    ['shipping' => $orderCollection->getTable('sales_order_address')],
                    "(main_table.entity_id = shipping.parent_id AND shipping.address_type = 'shipping')",
                    ['postcode', 'region', 'region_id', 'country_id']
                );
            if ($customerId) {
                $orderCollection->addFieldToFilter('customer_id', $customerId);
            }
            $this->_orderCollection = $orderCollection;
        }

        return $this->_orderCollection;
    }
}
