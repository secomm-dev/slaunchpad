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

namespace Mageplaza\ExtraFee\Block\Adminhtml\Order\Total;

use Magento\Framework\Currency\Data\Currency as CurrencyData;
use Magento\Framework\DataObject;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order;
use Magento\Tax\Model\Config;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class ExtraFee
 * @package Mageplaza\ExtraFee\Block\Adminhtml\Order\Total
 */
class ExtraFee extends \Mageplaza\ExtraFee\Block\Sales\Order\ExtraFee
{
    /**
     * Source object
     *
     * @var DataObject
     */
    protected $_source;

    /**
     * @var Config
     */
    protected $_taxConfig;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    public function __construct(
        Config $taxConfig,
        PriceCurrencyInterface $priceCurrency,
        Template\Context $context,
        Config $config,
        Data $helper,
        array $data = []
    ) {
        $this->_taxConfig    = $taxConfig;
        $this->priceCurrency = $priceCurrency;

        parent::__construct($context, $config, $helper, $data);
    }

    /**
     * Format value based on order currency
     *
     * @param null|float $value
     *
     * @return string
     * @since 102.1.0
     */
    public function formatValue($value)
    {
        /** @var Order $order */
        $order = $this->getSource()->getOrder();

        return $order->getOrderCurrency()->formatPrecision(
            $value,
            2,
            ['display' => CurrencyData::NO_SYMBOL],
            false,
            false
        );
    }

    /**
     * Get source object
     *
     * @return \Magento\Framework\DataObject
     */
    public function getSource()
    {
        $this->_source = $this->getParentBlock()->getSource();

        return $this->_source;
    }

    /**
     * @param $total
     *
     * @return float|int
     */
    public function getExtraFeeAmount($total)
    {
        $oriExtraFeeTotals  = $this->helper->getExtraFeeTotals($this->getOrder());
        $source             = $this->getSource();
        $fee                = $total['base_value'];
        if ($this->_taxConfig->displaySalesShippingInclTax($source->getOrder()->getStoreId())) {
            $fee = $total['base_value_incl_tax'];
        }
        foreach ($oriExtraFeeTotals as $extraFee) {
            $refundValue = isset($extraFee['refund_value']) ? $extraFee['refund_value'] : 0;
            if($total['code'] === $extraFee['code'] && $fee > $extraFee['base_value'] - $refundValue) {
                $fee = $extraFee['base_value'] - $refundValue;
                if ($this->_taxConfig->displaySalesShippingInclTax($source->getOrder()->getStoreId())) {
                    $fee = $extraFee['base_value_incl_tax'] - $refundValue;
                }
            }
        }
        return $this->priceCurrency->round($fee) * 1;
    }

    /**
     * Get label for shipping total based on configuration settings
     *
     * @return string
     */
    public function getExtraFeeLabel($total)
    {
        $source = $this->getSource();
        if ($this->_taxConfig->displaySalesShippingInclTax($source->getOrder()->getStoreId())) {
            $label = __($total['rule_label'] . ' - ' . $total['label'] . ' (Incl. Tax)');
        } elseif ($this->_taxConfig->displaySalesShippingBoth($source->getOrder()->getStoreId())) {
            $label = __($total['rule_label'] . ' - ' . $total['label'] . ' (Excl. Tax)');
        } else {
            $label = __($total['label']);
        }
        return $label;
    }
}
