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

namespace Mageplaza\ExtraFee\Block\Sales\Order;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Tax\Model\Config;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class ExtraFee
 * @package Mageplaza\ExtraFee\Block\Sales\Order
 */
class ExtraFee extends Template
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * ExtraFee constructor.
     *
     * @param Template\Context $context
     * @param Config $config
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Config $config,
        Data $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->config = $config;
        $this->helper = $helper;
    }

    /**
     * @return $this
     */
    public function initTotals()
    {
        $parent            = $this->getParentBlock();
        $order             = $this->getOrder();
        $store             = $order->getStore();
        $oriExtraFeeTotals = $this->helper->getExtraFeeTotals($order);
        $extraFeeTotals    = $this->getExtraFeeTotals($parent->getSource(), $order);

        if ($parent->getSource()->getEntityType() === 'order') {
            $extraFeeTotals = $oriExtraFeeTotals;
        }

        foreach ($extraFeeTotals as $extraFeeTotal) {
            if (!$extraFeeTotal['rf'] && $parent->getSource()->getEntityType() === 'creditmemo') {
                continue;
            }
            $feeBaseValue     = $extraFeeTotal['base_value'];
            $feeBaseValueIncl = $extraFeeTotal['base_value_incl_tax'];
            $feeValueIncl     = $extraFeeTotal['value_incl_tax'];
            $feeValueExcl     = $extraFeeTotal['value_excl_tax'];
            if ($this->checkCreditmemo($parent)) {
                foreach ($oriExtraFeeTotals as $oriExtraFeeTotal) {
                    $refundValue = isset($oriExtraFeeTotal['refund_value']) ? $oriExtraFeeTotal['refund_value'] : 0;
                    if ($oriExtraFeeTotal['code'] == $extraFeeTotal['code'] && $feeBaseValue > ($oriExtraFeeTotal['base_value'] - $refundValue)) {
                        $feeBaseValue     = $oriExtraFeeTotal['base_value'] - $refundValue;
                        $feeBaseValueIncl = $oriExtraFeeTotal['base_value_incl_tax'] - $refundValue * (1 + $oriExtraFeeTotal['percent'] / 100);
                        $feeValueIncl     = $oriExtraFeeTotal['value_incl_tax'] - $refundValue * (1 + $oriExtraFeeTotal['percent'] / 100);
                        $feeValueExcl     = $oriExtraFeeTotal['value_excl_tax'] - $refundValue;
                    }
                }
            }

            if ($extraFeeTotal['apply_type'] == 2) {
                $order->setTaxAmount($order->getTaxAmount() + ($extraFeeTotal['base_value_incl_tax'] - $extraFeeTotal['base_value']));
                $order->setBaseTaxAmount($order->getBaseTaxAmount() + ($extraFeeTotal['base_value_incl_tax'] - $extraFeeTotal['base_value']));
            }

            $totalIncl = new DataObject(
                [
                    'code'       => $extraFeeTotal['code'] . '_incl',
                    'value'      => $feeValueIncl,
                    'base_value' => $feeBaseValueIncl,
                    'label'      => ((strpos($extraFeeTotal['code'], 'auto') === false)
                            ? $extraFeeTotal['rule_label'] . ' - ' . $extraFeeTotal['label'] : $extraFeeTotal['label']) . __(' (Incl.Tax)'),
                    'block_name' => $this->checkCreditmemo($parent) ? $this->getNameInLayout() : ''
                ]
            );

            if ($this->config->displaySalesShippingBoth($store)) {
                $totalExcl = new DataObject(
                    [
                        'code'       => $extraFeeTotal['code'],
                        'value'      => $feeValueExcl,
                        'base_value' => $feeBaseValue,
                        'label'      => ((strpos($extraFeeTotal['code'], 'auto') === false)
                                ? $extraFeeTotal['rule_label'] . ' - ' . $extraFeeTotal['label'] : $extraFeeTotal['label']) . __(' (Excl.Tax)'),
                        'block_name' => $this->checkCreditmemo($parent) ? $this->getNameInLayout() : ''
                    ]
                );
                if ($this->checkCreditmemo($parent)) {
                    $parent->addTotal($totalExcl, 'subtotal_incl');
                } else {
                    $parent->addTotal($totalIncl, 'subtotal_incl');
                    $parent->addTotal($totalExcl, 'subtotal_incl');
                }
            } elseif ($this->config->displaySalesShippingInclTax($store)) {
                $parent->addTotal($totalIncl, 'subtotal_incl');
            } else {
                $parent->addTotal(new DataObject([
                    'code'       => $extraFeeTotal['code'],
                    'value'      => $feeValueExcl,
                    'base_value' => $feeBaseValue,
                    'label'      => (strpos($extraFeeTotal['code'], 'auto') === false)
                            ? $extraFeeTotal['rule_label'] . ' - ' . $extraFeeTotal['label'] : $extraFeeTotal['label'],
                    'block_name' => $this->checkCreditmemo($parent) ? $this->getNameInLayout() : ''
                ]), 'subtotal_incl');
            }
        }

        return $this;
    }

    /**
     * @return mixed
     */
    public function getOrder()
    {
        $parent = $this->getParentBlock();
        $source = $parent->getSource();
        if (!$source->getQuoteId()) {
            $source = $source->getOrder();
        }

        return $source;
    }

    /**
     * @param $source
     * @param $order
     *
     * @return array
     */
    public function getExtraFeeTotals($source, $order)
    {
        return $this->helper->getObjectExtraFeeTotals($source, $order);
    }

    /**
     * @param $parent
     *
     * @return bool
     */
    protected function checkCreditmemo($parent) {
        return $parent->getSource()->getEntityType() === 'creditmemo' && $this->getRequest()->getActionName() == 'new';
    }
}
