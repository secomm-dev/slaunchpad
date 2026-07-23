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
 * @package     Mageplaza_ThankYouPage
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ThankYouPage\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RuleFactory;

/**
 * Class CartRules
 * @package Mageplaza\ThankYouPage\Model\Config\Source
 */
class CartRules implements ArrayInterface
{
    /**
     * @var RuleFactory
     */
    protected $ruleFactory;

    /**
     * CartRules constructor.
     *
     * @param RuleFactory $ruleFactory
     */
    public function __construct(RuleFactory $ruleFactory)
    {
        $this->ruleFactory = $ruleFactory;
    }

    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        $option         = [['value' => 0, 'label' => __('-- Please Select --')]];
        $ruleCollection = $this->ruleFactory->create()->getCollection();
        foreach ($ruleCollection as $rule) {
            if ($rule->getIsActive() && $rule->getCouponType() == Rule::COUPON_TYPE_SPECIFIC && $rule->getUseAutoGeneration()) {
                $option[] = [
                    'value' => $rule->getId(),
                    'label' => $rule->getName()
                ];
            }
        }

        return $option;
    }
}
