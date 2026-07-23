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

namespace Mageplaza\ExtraFee\Block\Adminhtml\Order\Create;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Session\Quote;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Block\Adminhtml\Order\Create\AbstractCreate;
use Magento\Sales\Model\AdminOrder\Create;
use Magento\Tax\Helper\Data as TaxHelper;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class ExtraFee
 * @package Mageplaza\ExtraFee\Block\Adminhtml\Order\Create
 */
class ExtraFee extends AbstractCreate
{
    /**
     * @var TaxHelper
     */
    protected $taxHelper;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * ExtraFee constructor.
     *
     * @param Context $context
     * @param Quote $sessionQuote
     * @param Create $orderCreate
     * @param PriceCurrencyInterface $priceCurrency
     * @param TaxHelper $taxHelper
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Quote $sessionQuote,
        Create $orderCreate,
        PriceCurrencyInterface $priceCurrency,
        TaxHelper $taxHelper,
        Data $helper,
        array $data = []
    ) {
        $this->taxHelper = $taxHelper;
        $this->helper    = $helper;

        parent::__construct($context, $sessionQuote, $orderCreate, $priceCurrency, $data);
    }

    /**
     * @param $area
     *
     * @return array
     */
    public function getAppliedRule($area)
    {
        $quote = $this->getQuote();
        $this->helper->getCheckoutSession()->setMpArea($area);
        $quote->setTotalsCollectedFlag(false)->collectTotals();

        return $this->helper->getCheckoutSession()->getMpExtraFee() ?: [[], []];
    }

    /**
     * @param $rule
     *
     * @return mixed
     */
    public function getRuleLabel($rule)
    {
        $labels = $rule['labels'] ? Data::jsonDecode($rule['labels']) : [];

        return $labels[$this->getQuote()->getStoreId()] ?: $rule['name'];
    }

    /**
     * @param $rule
     *
     * @return array
     */
    public function getRuleOptions($rule)
    {
        $options = $rule['options'] ? Data::jsonDecode($rule['options'])['option']['value'] : [];

        return $options;
    }

    /**
     * @param $option
     *
     * @return mixed
     */
    public function getOptionLabel($option)
    {
        return $option[$this->getQuote()->getStoreId()] ?: $option[0];
    }

    /**
     * @param $option
     *
     * @return float|string
     */
    public function getOptionValueLabel($option)
    {
        $excl = $this->priceCurrency->format($option['calculated_amount']);
        $incl = $this->priceCurrency->format($option['calculated_amount_incl_tax']);
        if ($incl !== $excl && $this->taxHelper->displayShippingBothPrices()) {
            $excl .= __(' (Incl. Tax ') . $incl . ')';
        }

        return $excl;
    }

    /**
     * @param $ruleId
     * @param $key
     * @param $selectedOptions
     *
     * @return bool
     */
    public function isChecked($ruleId, $key, $selectedOptions)
    {
        return isset($selectedOptions['rule'][$ruleId])
            ? (isset($selectedOptions['rule'][$ruleId][$key]) || $selectedOptions['rule'][$ruleId] === $key)
            : false;
    }

    /**
     * Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('order_create_extra_fee_form');
    }
}
