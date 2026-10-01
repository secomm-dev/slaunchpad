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

namespace Mageplaza\RMA\Model\Order\Rule\Condition;

use Magento\Rule\Model\Condition\Combine as ConditionCombine;
use Magento\Rule\Model\Condition\Context;

/**
 * Class Combine
 * @package Mageplaza\RMA\Model\Order\Rule\Condition
 */
class Combine extends ConditionCombine
{
    /**
     * @var Order
     */
    protected $_conditionOrder;

    /**
     * Combine constructor.
     *
     * @param Context $context
     * @param Order $conditionOrder
     * @param array $data
     */
    public function __construct(
        Context $context,
        Order $conditionOrder,
        array $data = []
    ) {
        $this->_conditionOrder = $conditionOrder;

        parent::__construct($context, $data);

        $this->setType(self::class);
    }

    /**
     * Get new child select options
     *
     * @return array
     */
    public function getNewChildSelectOptions()
    {
        $orderAttributes = $this->_conditionOrder->loadAttributeOptions()->getAttributeOption();
        $attributes = [];
        /** @var array $orderAttributes */
        foreach ($orderAttributes as $code => $label) {
            $attributes[] = [
                'value' => 'Mageplaza\RMA\Model\Order\Rule\Condition\Order|' . $code,
                'label' => $label,
            ];
        }

        $conditions = parent::getNewChildSelectOptions();
        $conditions = array_merge_recursive($conditions, [
            ['label' => __('Order Attribute'), 'value' => $attributes]
        ]);

        return $conditions;
    }
}
