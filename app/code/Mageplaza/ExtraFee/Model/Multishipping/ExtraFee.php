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

namespace Mageplaza\ExtraFee\Model\Multishipping;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote\Address;
use Mageplaza\ExtraFee\Helper\Data;
use Mageplaza\ExtraFee\Model\Config\Source\ApplyType;
use Mageplaza\ExtraFee\Model\Config\Source\DisplayArea;
use Mageplaza\ExtraFee\Model\Rule;
use Mageplaza\ExtraFee\Model\RuleFactory;
use Mageplaza\ExtraFee\Model\Total\Quote\ExtraFee as QuoteExtraFee;

/**
 * Class ExtraFee
 * @package Mageplaza\ExtraFee\Model\Multishipping
 */
class ExtraFee
{
    /**
     * @var RuleFactory
     */
    protected $ruleFactory;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var QuoteExtraFee
     */
    protected $quoteExtraFee;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * ExtraFee constructor.
     *
     * @param CustomerSession $customerSession
     * @param RuleFactory $ruleFactory
     * @param QuoteExtraFee $quoteExtraFee
     * @param Data $helperData
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        CustomerSession $customerSession,
        RuleFactory $ruleFactory,
        QuoteExtraFee $quoteExtraFee,
        Data $helperData,
        PriceCurrencyInterface $priceCurrency
    ) {
        $this->customerSession = $customerSession;
        $this->ruleFactory     = $ruleFactory;
        $this->quoteExtraFee   = $quoteExtraFee;
        $this->helperData      = $helperData;
        $this->priceCurrency   = $priceCurrency;
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
        $result   = $this->quoteExtraFee->calculateAutoExtraFee($address, true, true);
        $extraFee = $this->helperData->getMpExtraFee($address);

        if (empty($extraFee)) {
            $this->helperData->setMpExtraFee($address, $result, DisplayArea::TOTAL);
        }

        $areaArray = explode(',', $area);
        $storeId   = $address->getQuote()->getStoreId();

        foreach ($areaArray as $it) {
            if ($it) {
                $this->getApplyRule($address, $it);
            }
            $extraFee  = $this->helperData->getMpExtraFee($address);
            $applyRule = $this->getAllApplyRule($address, $it);
            foreach ($extraFee as $ruleId => $option) {
                if (!isset($applyRule[$ruleId])) {
                    continue;
                }
                /** @var Rule $rule */
                $rule     = $this->ruleFactory->create()->load($ruleId);
                $options  = $rule->getOptions() ? Data::jsonDecode($rule->getOptions())['option']['value'] : [];
                $taxClass = $rule->getFeeTax();

                if (is_array($option)) {
                    foreach ($option as $item) {
                        [$baseRuleFeeAmount, $ruleFeeAmount, $baseRuleFeeAmountInclTax, $ruleFeeAmountInclTax] =
                            $this->quoteExtraFee->calculateExtraFeeAmount($address, $options[$item], $taxClass, $rule);
                        $result[] = [
                            'code'                => "mp_extra_fee_rule_{$ruleId}_{$item}",
                            'title'               => __($options[$item][$storeId] ?: $options[$item][0]),
                            'label'               => __($options[$item][$storeId] ?: $options[$item][0]),
                            'value'               => $ruleFeeAmount,
                            'base_value'          => $baseRuleFeeAmount,
                            'base_value_incl_tax' => $baseRuleFeeAmountInclTax,
                            'value_incl_tax'      => $ruleFeeAmountInclTax,
                            'value_excl_tax'      => $ruleFeeAmount,
                            'rf'                  => $rule->getRefundable(),
                            'display_area'        => $rule->getArea() ?: '3',
                            'apply_type'          => $rule->getApplyType(),
                            'rule_label'          => $this->helperData->getRuleLabel($rule, $storeId)
                        ];
                    }
                } else {
                    [$baseRuleFeeAmount, $ruleFeeAmount, $baseRuleFeeAmountInclTax, $ruleFeeAmountInclTax] =
                        $this->quoteExtraFee->calculateExtraFeeAmount($address, $options[$option], $taxClass, $rule);
                    $result[] = [
                        'code'                => "mp_extra_fee_rule_{$ruleId}_{$option}",
                        'title'               => __($options[$option][$storeId] ?: $options[$option][0]),
                        'label'               => __($options[$option][$storeId] ?: $options[$option][0]),
                        'value'               => $ruleFeeAmount,
                        'base_value'          => $baseRuleFeeAmount,
                        'value_incl_tax'      => $ruleFeeAmountInclTax,
                        'value_excl_tax'      => $ruleFeeAmount,
                        'base_value_incl_tax' => $baseRuleFeeAmountInclTax,
                        'rf'                  => $rule->getRefundable(),
                        'display_area'        => $rule->getArea() ?: '3',
                        'apply_type'          => $rule->getApplyType(),
                        'rule_label'          => $this->helperData->getRuleLabel($rule, $storeId)
                    ];
                }
            }
        }

        $this->helperData->setMpExtraFee($address, $result, DisplayArea::TOTAL);
        $address->save();
    }

    /**
     * @param Address $address
     * @param string $area
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getApplyRule($address, $area)
    {
        $area            = explode(',', $area);
        $applyRule       = [];
        $selectedOptions = [];
        foreach ($area as $item) {
            [$applyRule[], $selectedOptions[]] = $this->quoteExtraFee->checkApplyRule($address, $item, true);
        }

        return [$applyRule, $selectedOptions];
    }

    /**
     * @param Address $address
     * @param string $area
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getAllApplyRule($address, $area)
    {
        $quote          = $address->getQuote();
        $ruleCollection = $this->ruleFactory->create()->getCollection()->setOrder('priority', 'ASC');
        if ($area) {
            $ruleCollection->addFieldToFilter('area', $area);
        }
        $applyRule = [];

        /** @var Rule $rule */
        foreach ($ruleCollection as $rule) {
            $stores         = explode(',', $rule->getStoreIds());
            $customerGroups = explode(',', $rule->getCustomerGroups());
            if ($this->customerSession->isLoggedIn()) {
                $customerGroupId = $this->customerSession->getCustomerGroupId();
            } else {
                $customerGroupId = 0;
            }
            if (!$rule->getStatus()
                || !in_array($customerGroupId, $customerGroups, false)
                || !(in_array($quote->getStoreId(), $stores, false) || in_array('0', $stores, true))
            ) {
                continue;
            }

            if ($rule->validate($address)) {
                if ((int) $rule->getApplyType() === ApplyType::AUTOMATIC) {
                    if ($rule->getStopFurtherProcessing()) {
                        break;
                    }
                    continue;
                }

                $options = Data::jsonDecode($rule->getOptions());
                if (!isset($options['option'])
                    || empty($options['option']['value'])
                    || !is_array($options['option']['value'])
                ) {
                    continue;
                }
                foreach ($options['option']['value'] as &$option) {
                    $object = $quote;
                    if (in_array($address->getAddressType(), ['shipping', 'billing'], true)) {
                        $object = $address;
                    }
                    [$baseRuleFeeAmount, $ruleFeeAmount, $baseRuleFeeAmountInclTax, $ruleFeeAmountInclTax]
                        = $this->quoteExtraFee->calculateExtraFeeAmount($object, $option, $rule->getFeeTax(), $rule);
                    $option['calculated_amount']                 = $ruleFeeAmount;
                    $option['calculated_amount_format']          = $this->priceCurrency->format($ruleFeeAmount);
                    $option['calculated_amount_incl_tax']        = $ruleFeeAmountInclTax;
                    $option['calculated_amount_incl_tax_format'] = $this->priceCurrency->format($ruleFeeAmountInclTax);

                }
                unset($option);
                $rule->setOptions(Data::jsonEncode($options));
                $rule->setAddressId($address->getId());

                $checkoutSession = $this->helperData->getCheckoutSession();
                $extraFeeNote    = $checkoutSession->getExtraFeeMultiNote() ?: [];

                if (count($extraFeeNote) && isset($extraFeeNote[$address->getId()])) {
                    $rule->setNote($extraFeeNote[$address->getId()]);
                }

                $applyRule[$rule->getId()] = $rule->getData();
                if ($rule->getStopFurtherProcessing()) {
                    break;
                }
            }
        }

        return $applyRule;
    }
}
