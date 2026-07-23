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

namespace Mageplaza\ExtraFee\Plugin\Adminhtml\Order\Totals;

use Magento\Sales\Block\Adminhtml\Order\Totals\Tax;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class TaxPlugin
 * @package Mageplaza\ExtraFee\Plugin\Adminhtml\Order\Totals
 */
class TaxPlugin
{
    /**
     * @var Data
     */
    private $helperData;

    /**
     * TaxPlugin constructor.
     *
     * @param Data $helperData
     */
    public function __construct(
        Data $helperData
    ) {
        $this->helperData = $helperData;
    }

    /**
     * Calculate fee tax amount
     *
     * @param Tax $subject
     * @param $result
     *
     * @return mixed
     */
    public function afterGetFullTaxInfo(Tax $subject, $result)
    {
        $source = $subject->getSource();
        if (!$source instanceof Invoice
            && !$source instanceof Creditmemo
        ) {
            $source = $subject->getOrder();
        }

        if ($source instanceof Invoice) {
            $order = $subject->getOrder();

            $extraFee = $this->helperData->getMpExtraFee($order, 4);
            if (isset($extraFee[0])) {
                $extraFee = $extraFee[0];
                $result[] = [
                    'tax_amount'      => $extraFee['value_incl_tax'] - $extraFee['value_excl_tax'],
                    'base_tax_amount' => $extraFee['value_incl_tax'] - $extraFee['value_excl_tax'],
                    'title'           => $extraFee['rule_label'] . ' - ' . $extraFee['title'],
                    'percent'         => $extraFee['percent'],
                ];
                if ($extraFee['apply_type'] == 2) {
                    $source->setTaxAmount($source->getTaxAmount() + ($extraFee['base_value_incl_tax'] - $extraFee['base_value']));
                    $source->setBaseTaxAmount($source->getBaseTaxAmount() + ($extraFee['base_value_incl_tax'] - $extraFee['base_value']));
                }
            }
        } elseif ($source instanceof Creditmemo) {
            $order    = $source->getOrder();
            $extraFee = $this->helperData->getMpExtraFee($order, 4);
            if (isset($extraFee[0])) {
                $extraFee = $extraFee[0];
                $result[] = [
                    'title'           => $extraFee['rule_label'] . ' - ' . $extraFee['title'],
                    'percent'         => $extraFee['percent'],
                    'tax_amount'      => $extraFee['value_incl_tax'] - $extraFee['value_excl_tax'],
                    'base_tax_amount' => $extraFee['value_incl_tax'] - $extraFee['value_excl_tax'],
                ];
                if ($extraFee['apply_type'] == 2) {
                    $source->setTaxAmount($source->getTaxAmount() + ($extraFee['base_value_incl_tax'] - $extraFee['base_value']));
                    $source->setBaseTaxAmount($source->getBaseTaxAmount() + ($extraFee['base_value_incl_tax'] - $extraFee['base_value']));
                }
            }
        }

        return $result;
    }
}
