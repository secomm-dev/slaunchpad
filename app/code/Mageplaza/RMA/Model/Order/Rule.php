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

namespace Mageplaza\RMA\Model\Order;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Model\ResourceModel\Iterator;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Rule\Model\AbstractModel;
use Magento\Rule\Model\Action\Collection;
use Magento\Rule\Model\Condition\Combine;
use Magento\Sales\Model\OrderFactory;
use Mageplaza\RMA\Model\Order\Rule\Condition\CombineFactory as CondCombineFactory;

/**
 * Class Rule
 * @package Mageplaza\RMA\Model\Order
 */
class Rule extends AbstractModel
{
    /**
     * @var array
     */
    protected $_orderIds;

    /**
     * @var Iterator
     */
    protected $_resourceIterator;

    /**
     * @var CondCombineFactory
     */
    protected $_orderCondCombineFact;

    /**
     * @var OrderFactory
     */
    protected $_orderFactory;

    /**
     * Rule constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param TimezoneInterface $localeDate
     * @param CondCombineFactory $combineFactory
     * @param Iterator $resourceIterator
     * @param OrderFactory $orderFactory
     * @param AbstractDb|null $resourceCollection
     * @param AbstractResource|null $resource
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        TimezoneInterface $localeDate,
        CondCombineFactory $combineFactory,
        Iterator $resourceIterator,
        OrderFactory $orderFactory,
        ?AbstractDb $resourceCollection = null,
        ?AbstractResource $resource = null
    ) {
        $this->_resourceIterator     = $resourceIterator;
        $this->_orderCondCombineFact = $combineFactory;
        $this->_orderFactory         = $orderFactory;

        parent::__construct(
            $context,
            $registry,
            $formFactory,
            $localeDate,
            $resource,
            $resourceCollection
        );
    }

    /**
     * @return Combine|Rule\Condition\Combine
     */
    public function getConditionsInstance()
    {
        return $this->_orderCondCombineFact->create();
    }

    /**
     * Get rule condition product combine model instance
     *
     * @return Collection|Combine|Rule\Condition\Combine
     */
    public function getActionsInstance()
    {
        return $this->getConditionsInstance();
    }

    /**
     * Get array of order ids which are matched by label rule
     *
     * @param \Magento\Sales\Model\ResourceModel\Order\Collection $orderCollection
     *
     * @return array|null
     */
    public function getMatchingOrderIds($orderCollection)
    {
        if ($this->_orderIds === null) {
            $this->_orderIds = [];

            $this->_resourceIterator->walk($orderCollection->getSelect(), [[$this, 'callbackValidateOrder']], [
                'order' => $this->_orderFactory->create()
            ]);
        }

        return $this->_orderIds;
    }

    /**
     * Callback function for order matching
     *
     * @param array $args
     *
     * @return void
     */
    public function callbackValidateOrder($args)
    {
        $order = clone $args['order'];
        $order->setData($args['row']);

        if ($this->getConditions()->validate($order)) {
            $this->_orderIds[] = $order->getId();
        }
    }
}
