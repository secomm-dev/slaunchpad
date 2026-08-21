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

namespace Mageplaza\ExtraFee\Model\Rule\Condition;

use Magento\Framework\DataObject;
use Magento\Rule\Model\Condition\Context;
use Magento\SalesRule\Model\Rule\Condition\Address;
use Magento\SalesRule\Model\Rule\Condition\Combine as SalesRuleCombine;
use Magento\SalesRule\Model\Rule\Condition\Product\Found;
use Magento\SalesRule\Model\Rule\Condition\Product\Subselect;

/**
 * Class Combine
 * @package Mageplaza\ExtraFee\Model\Rule\Condition
 */
class Combine extends \Magento\Rule\Model\Condition\Combine
{
    /**
     * @var Address
     */
    protected $conditionAddress;

    /**
     * @var Customer
     */
    protected $customerCondition;

    /**
     * @var Advanced
     */
    protected $advancedCondition;

    /**
     * @var \Magento\SalesRule\Model\Rule\Condition\Product
     */
    protected $productCondition;

    /**
     * Combine constructor.
     *
     * @param Context $context
     * @param Address $conditionAddress
     * @param \Magento\SalesRule\Model\Rule\Condition\Product $productCondition
     * @param Advanced $advancedCondition
     * @param Customer $customerCondition
     * @param array $data
     */
    public function __construct(
        Context $context,
        Address $conditionAddress,
        \Magento\SalesRule\Model\Rule\Condition\Product $productCondition,
        Advanced $advancedCondition,
        Customer $customerCondition,
        array $data = []
    ) {
        $this->conditionAddress  = $conditionAddress;
        $this->customerCondition = $customerCondition;
        $this->advancedCondition = $advancedCondition;
        $this->productCondition  = $productCondition;

        parent::__construct($context, $data);
    }

    /**
     * @return array
     */
    public function getNewChildSelectOptions()
    {
        $addressAttributes = $this->conditionAddress->loadAttributeOptions()->getAttributeOption();
        $attributes        = [];
        foreach ($addressAttributes as $code => $label) {
            $attributes[] = [
                'value' => 'Magento\SalesRule\Model\Rule\Condition\Address|' . $code,
                'label' => $label,
            ];
        }

        $customerAttributes = $this->customerCondition->loadAttributeOptions()->getAttributeOption();
        $customerAttr       = [];
        foreach ($customerAttributes as $code => $label) {
            $customerAttr[] = [
                'value' => 'Mageplaza\ExtraFee\Model\Rule\Condition\Customer|' . $code,
                'label' => $label,
            ];
        }

        $productAttributes = $this->productCondition->loadAttributeOptions()->getAttributeOption();
        $productAttr       = [];
        foreach ($productAttributes as $code => $label) {
            $productAttr[] = [
                'value' => 'Mageplaza\ExtraFee\Model\Rule\Condition\Product|' . $code,
                'label' => $label,
            ];
        }

        $advancedAttributes = $this->advancedCondition->loadAttributeOptions()->getAttributeOption();
        $advancedAttr       = [];
        foreach ($advancedAttributes as $code => $label) {
            $advancedAttr[] = [
                'value' => 'Mageplaza\ExtraFee\Model\Rule\Condition\Advanced|' . $code,
                'label' => $label,
            ];
        }

        $conditions = parent::getNewChildSelectOptions();
        $conditions = array_merge_recursive(
            $conditions,
            [
                [
                    'value' => Found::class,
                    'label' => __('Product attribute combination'),
                ],
                [
                    'value' => Subselect::class,
                    'label' => __('Products subselection')
                ],
                [
                    'value' => SalesRuleCombine::class,
                    'label' => __('Conditions combination')
                ],
                ['label' => __('Other Attribute'), 'value' => $advancedAttr],
                ['label' => __('Cart Attribute'), 'value' => $attributes],
                ['label' => __('Customer Attribute'), 'value' => $customerAttr],
                ['label' => __('Product Attribute'), 'value' => $productAttr]
            ]
        );

        $additional           = new DataObject();
        $additionalConditions = $additional->getConditions();
        if ($additionalConditions) {
            $conditions = array_merge_recursive($conditions, $additionalConditions);
        }

        return $conditions;
    }
}
