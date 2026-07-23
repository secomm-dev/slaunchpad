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

namespace Mageplaza\ExtraFee\Plugin\Quote\Model\Cart;

use Closure;
use Magento\Quote\Api\Data\TotalSegmentExtensionFactory;

/**
 * Class TotalsConverter
 * @package Mageplaza\ExtraFee\Plugin\Quote\Model\Cart
 */
class TotalsConverter
{
    /**
     * @var TotalSegmentExtensionFactory
     */
    protected $totalSegmentExtensionFactory;

    /**
     * @param TotalSegmentExtensionFactory $totalSegmentExtensionFactory
     */
    public function __construct(TotalSegmentExtensionFactory $totalSegmentExtensionFactory)
    {
        $this->totalSegmentExtensionFactory = $totalSegmentExtensionFactory;
    }

    /**
     * @param \Magento\Quote\Model\Cart\TotalsConverter $subject
     * @param Closure $proceed
     * @param array $addressTotals
     *
     * @return mixed
     */
    public function aroundProcess(
        \Magento\Quote\Model\Cart\TotalsConverter $subject,
        Closure $proceed,
        array $addressTotals = []
    ) {
        $totalSegments = $proceed($addressTotals);

        foreach ($addressTotals as $addressTotal) {
            if (strpos($addressTotal->getCode(), 'mp_extra_fee') !== false
                && isset($totalSegments[$addressTotal->getCode()])
            ) {
                $attributes = $totalSegments[$addressTotal->getCode()]->getExtensionAttributes();
                if ($attributes === null) {
                    $attributes = $this->totalSegmentExtensionFactory->create();
                }
                $attributes->setData('value_incl_tax', $addressTotal->getValueInclTax());
                $attributes->setData('rule_label', $addressTotal->getRuleLabel());
                $totalSegments[$addressTotal->getCode()]->setExtensionAttributes($attributes);

                $totalSegments[$addressTotal->getCode()]->setValueInclTax($addressTotal->getValueInclTax());
            }
        }

        return $totalSegments;
    }
}
