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

namespace Mageplaza\ExtraFee\Model\Sales\Pdf;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order\Pdf\Total\DefaultTotal;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;
use Magento\Tax\Model\ResourceModel\Sales\Order\Tax\CollectionFactory;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class ExtraFee
 * @package Mageplaza\ExtraFee\Model\Sales\Pdf
 */
class ExtraFee extends DefaultTotal
{
    /**
     * @var Config
     */
    protected $_taxConfig;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * ExtraFee constructor.
     *
     * @param TaxHelper $taxHelper
     * @param Calculation $taxCalculation
     * @param CollectionFactory $ordersFactory
     * @param Config $taxConfig
     * @param PriceCurrencyInterface $priceCurrency
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        TaxHelper $taxHelper,
        Calculation $taxCalculation,
        CollectionFactory $ordersFactory,
        Config $taxConfig,
        PriceCurrencyInterface $priceCurrency,
        Data $helper,
        array $data = []
    ) {
        $this->_taxConfig    = $taxConfig;
        $this->priceCurrency = $priceCurrency;
        $this->helper        = $helper;

        parent::__construct($taxHelper, $taxCalculation, $ordersFactory, $data);
    }

    /**
     * Get array of arrays with totals information for display in PDF
     * array(
     *  $index => array(
     *      'amount'   => $amount,
     *      'label'    => $label,
     *      'font_size'=> $font_size
     *  )
     * )
     * @return array
     */
    public function getTotalsForDisplay()
    {
        $source = $this->getSource();
        $type   = $source->getEntityType();
        $order  = $this->getOrder();
        if ($type === 'invoice') {
            if ($this->helper->isInvoiced($order) !== $source->getId()) {
                return [];
            }
        }
        $store          = $this->getOrder()->getStore();
        $fontSize       = $this->getFontSize() ?: 7;
        $extraFeeTotals = $this->helper->getObjectExtraFeeTotals($source, $order);
        $totals         = [];
        $currency       = $this->getOrder()->getOrderCurrency();
        foreach ($extraFeeTotals as $fee) {
            if ($type === 'creditmemo' && $fee['rf'] !== '1') {
                continue;
            }
            $amount        = $this->priceCurrency->format($fee['value_excl_tax'], false, 2, null, $currency);
            $amountInclTax = $this->priceCurrency->format($fee['value_incl_tax'], false, 2, null, $currency);
            if ($this->_taxConfig->displaySalesShippingBoth($store)) {
                $totals[] = [
                    'amount'    => $this->getAmountPrefix() . $amount,
                    'label'     => $fee['rule_label']
                        . ($fee['label'] ? ' - ' . $fee['label'] : '') . __(' (Excl. Tax)') . ':',
                    'font_size' => $fontSize,
                ];
                $totals[] = [
                    'amount'    => $this->getAmountPrefix() . $amountInclTax,
                    'label'     => $fee['rule_label']
                        . ($fee['label'] ? ' - ' . $fee['label'] : '') . __(' (Excl. Tax)') . ':',
                    'font_size' => $fontSize,
                ];
            } elseif ($this->_taxConfig->displaySalesShippingInclTax($store)) {
                $totals[] = [
                    'amount'    => $this->getAmountPrefix() . $amountInclTax,
                    'label'     => $fee['rule_label']
                        . ($fee['label'] ? ' - ' . $fee['label'] : '') . ':',
                    'font_size' => $fontSize,
                ];
            } else {
                $totals[] = [
                    'amount'    => $this->getAmountPrefix() . $amount,
                    'label'     => $fee['rule_label']
                        . ($fee['label'] ? ' - ' . $fee['label'] : '') . ':',
                    'font_size' => $fontSize,
                ];
            }
        }

        return $totals;
    }
}
