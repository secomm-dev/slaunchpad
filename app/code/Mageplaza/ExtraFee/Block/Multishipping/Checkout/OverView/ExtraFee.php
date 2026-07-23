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

namespace Mageplaza\ExtraFee\Block\Multishipping\Checkout\OverView;

use Exception;
use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Multishipping\Block\Checkout\Overview;
use Magento\Multishipping\Model\Checkout\Type\Multishipping;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Quote\Model\Quote\TotalsReader;
use Magento\Tax\Helper\Data;
use Mageplaza\ExtraFee\Helper\Data as ExtraFeeData;
use Mageplaza\ExtraFee\Model\Multishipping\ExtraFee as MultishippingExtraFee;

/**
 * Class ExtraFee
 * @package Mageplaza\ExtraFee\Block\Multishipping\Checkout\OverView
 */
class ExtraFee extends Overview
{
    /**
     * @var ExtraFeeData
     */
    protected $helperData;

    /**
     * @var MultishippingExtraFee
     */
    protected $multiShippingExtraFee;

    /**
     * ExtraFee constructor.
     *
     * @param Context $context
     * @param Multishipping $multishipping
     * @param Data $taxHelper
     * @param PriceCurrencyInterface $priceCurrency
     * @param TotalsCollector $totalsCollector
     * @param TotalsReader $totalsReader
     * @param ExtraFeeData $helperData
     * @param MultishippingExtraFee $multiShippingExtraFee
     * @param array $data
     * @param CheckoutHelper|null $checkoutHelper
     */
    public function __construct(
        Context $context,
        Multishipping $multishipping,
        Data $taxHelper,
        PriceCurrencyInterface $priceCurrency,
        TotalsCollector $totalsCollector,
        TotalsReader $totalsReader,
        ExtraFeeData $helperData,
        MultishippingExtraFee $multiShippingExtraFee,
        array $data = [],
        ?CheckoutHelper $checkoutHelper = null
    ) {
        $this->helperData            = $helperData;
        $this->multiShippingExtraFee = $multiShippingExtraFee;

        parent::__construct(
            $context,
            $multishipping,
            $taxHelper,
            $priceCurrency,
            $totalsCollector,
            $totalsReader,
            $data,
            $checkoutHelper
        );
    }

    /**
     * @param Address $address
     * @param string $area
     *
     * @return array
     */
    public function getExtraFeeInfo($address, $area)
    {
        $extraFeeTotals = $this->helperData->getExtraFeeTotals($address);
        $result         = [];
        foreach ($extraFeeTotals as $fee) {
            if ($fee['display_area'] === $area) {
                $result[] = $fee;
            }
        }

        return $result;
    }

    /**
     * @param array $fee
     *
     * @return string
     */
    public function getFeeTitle($fee)
    {
        $result = "{$this->escapeHtml($fee['rule_label'])}"
            . ((strpos($fee['code'], 'auto') === false)
                ? " - {$this->escapeHtml($fee['label'])}" : $this->escapeHtml($fee['label'])) . ' ';

        $store = $this->getQuote()->getStore();

        $baseRuleFeeAmount        =
            $this->priceCurrency->format($fee['base_value'], true, 2, null, $store->getBaseCurrency());
        $baseRuleFeeAmountInclTax =
            $this->priceCurrency->format($fee['base_value_incl_tax'], true, 2, null, $store->getBaseCurrency());
        $ruleFeeAmountInclTax     =
            $this->priceCurrency->format($fee['value_incl_tax'], true, 2, null, $store->getOrderCurrency());
        if ($this->_taxHelper->displayShippingPriceIncludingTax()) {
            $excl = "{$baseRuleFeeAmountInclTax} [{$ruleFeeAmountInclTax}] ";
        } else {
            $excl = "{$baseRuleFeeAmount} ";
        }
        $incl   = "{$baseRuleFeeAmountInclTax} ";
        $result .= $excl;
        if ($incl !== $excl && $this->_taxHelper->displayShippingBothPrices()) {
            $result .= ' (' . $this->escapeHtml(__('Incl. Tax ')) . $incl . ')';
        }

        return $result;
    }

    /**
     * @param Address $address
     * @param string $area
     *
     * @return array
     */
    public function getAllApplyRule($address, $area)
    {
        try {
            return $this->multiShippingExtraFee->getAllApplyRule($address, $area);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * @param Address $address
     * @param int $area
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getAppliedRule($address, $area)
    {
        return $this->multiShippingExtraFee->getApplyRule($address, $area);
    }

    /**
     * @param Address $address
     * @param string $area
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function fetch($address, $area)
    {
        $this->multiShippingExtraFee->fetch($address, $area);
    }

    /**
     * @param array $rule
     * @param int $storeId
     *
     * @return array
     */
    public function getOptions($rule, $storeId)
    {
        $options = [];
        if (isset($rule['options'])) {
            $option = ExtraFeeData::jsonDecode($rule['options']);
            foreach ($option['option']['value'] as $key => $opt) {
                $options[] = [
                    'rule_id' => $rule['rule_id'],
                    'value'   => $key,
                    'title'   => $opt[$storeId]
                        ?: $opt[0] . ' ' . $this->priceCurrency->format($opt['calculated_amount']),
                    'type'    => $rule['display_type'],
                ];
            }

            return $options;
        }

        return $options;
    }

    /**
     * @param array $rule
     * @param int $storeId
     *
     * @return mixed
     */
    public function getRuleTitle($rule, $storeId)
    {
        $label = ExtraFeeData::jsonDecode($rule['labels']);

        return $label[$storeId] ?: $rule['name'];
    }

    /**
     * @param int $ruleId
     * @param int $key
     * @param array $selectedOptions
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
     * @return string
     */
    public function getExtraFeeUrl()
    {
        return $this->getUrl('mpextrafee/update/update');
    }

    /**
     * @return bool
     */
    public function hasVirtualItems()
    {
        return $this->multishipping->getQuote()->hasVirtualItems();
    }

    /**
     * @return array
     */
    public function getExtraFeeNote()
    {
        $checkoutSession = $this->helperData->getCheckoutSession();

        return $checkoutSession->getExtraFeeMultiNote() ?: [];
    }
}
