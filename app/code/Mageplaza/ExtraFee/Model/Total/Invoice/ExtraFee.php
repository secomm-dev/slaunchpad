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

namespace Mageplaza\ExtraFee\Model\Total\Invoice;

use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class ExtraFee
 * @package Mageplaza\ExtraFee\Model\Total\Invoice
 */
class ExtraFee extends AbstractTotal
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * ExtraFee constructor.
     *
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        Data $helper,
        array $data = []
    ) {
        $this->helper = $helper;

        parent::__construct($data);
    }

    /**
     * Collect invoice subtotal
     *
     * @param Invoice $invoice
     *
     * @return $this
     */
    public function collect(Invoice $invoice)
    {
        $order          = $invoice->getOrder();
        $extraFeeTotals = $this->helper->getObjectExtraFeeTotals($invoice, $order);

        foreach ($extraFeeTotals as $fee) {
            $feeValue     = $fee['apply_type'] == 2 ? $fee['value_incl_tax'] : $fee['value'];
            $baseFeeValue = $fee['apply_type'] == 2 ? $fee['base_value_incl_tax'] : $fee['base_value'];
            $invoice->setGrandTotal($invoice->getGrandTotal() + $feeValue);
            $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseFeeValue);
        }

        return $this;
    }
}
