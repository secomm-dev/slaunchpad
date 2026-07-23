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

namespace Mageplaza\ExtraFee\Plugin\Multishipping\Type;

use Magento\Checkout\Api\Exception\PaymentProcessingRateLimitExceededException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Multishipping\Model\Checkout\Type\Multishipping as CheckoutMultishipping;
use Magento\Quote\Model\Quote\Address;
use Mageplaza\ExtraFee\Helper\Data;
use Mageplaza\ExtraFee\Model\Config\Source\DisplayArea;
use Mageplaza\ExtraFee\Model\Multishipping\ExtraFee;
use Mageplaza\ExtraFee\Model\RuleFactory;

/**
 * Class Multishipping
 * @package Mageplaza\ExtraFee\Plugin\Multishipping\Type
 */
class Multishipping
{
    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var RuleFactory
     */
    protected $ruleFactory;

    /**
     * @var ExtraFee
     */
    protected $multishippingExtraFee;

    /**
     * Multishipping constructor.
     *
     * @param Data $helperData
     * @param RequestInterface $request
     * @param RuleFactory $ruleFactory
     * @param ExtraFee $multishippingExtraFee
     */
    public function __construct(
        Data $helperData,
        RequestInterface $request,
        RuleFactory $ruleFactory,
        ExtraFee $multishippingExtraFee
    ) {
        $this->helperData            = $helperData;
        $this->request               = $request;
        $this->ruleFactory           = $ruleFactory;
        $this->multishippingExtraFee = $multishippingExtraFee;
    }

    /**
     * @param CheckoutMultishipping $subject
     * @param CheckoutMultishipping $result
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function afterSetShippingMethods(CheckoutMultishipping $subject, $result)
    {
        if ($this->helperData->isEnabled($result->getQuote()->getStoreId())) {
            $rules        = $this->request->getParam('rule');
            $ruleRequired = $this->request->getParam('rule_required');
            $quote        = $result->getQuote();
            $addresses    = $quote->getAllShippingAddresses();

            if ($ruleRequired) {
                foreach ($ruleRequired as $addressId => $required) {
                    foreach ($required as $ruleId) {
                        if (!isset($rules[$addressId][$ruleId])) {
                            throw new LocalizedException(
                                __('Please choose at least one option for each require extra fee')
                            );
                        }
                    }
                }
            }

            /** @var  Address $address */
            foreach ($addresses as $address) {
                $addressId        = $address->getId();
                $extraFeeShipping = isset($rules[$addressId]) ? $rules[$addressId] : [];
                $this->helperData->setMpExtraFee(
                    $address,
                    http_build_query(['rule' => $extraFeeShipping]),
                    DisplayArea::SHIPPING_METHOD
                );
                $this->multishippingExtraFee->fetch($address, DisplayArea::SHIPPING_METHOD);
            }
        }

        return $result;
    }

    /**
     * @param CheckoutMultishipping $subject
     * @param CheckoutMultishipping $result
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function afterSetPaymentMethod(CheckoutMultishipping $subject, $result)
    {
        if ($this->helperData->isEnabled($result->getQuote()->getStoreId())) {
            $rules        = $this->request->getParam('rule');
            $ruleRequired = $this->request->getParam('rule_required');
            $quote        = $result->getQuote();

            if ($ruleRequired) {
                foreach ($ruleRequired as $addressId => $required) {
                    foreach ($required as $ruleId) {
                        if (!isset($rules[$addressId][$ruleId])) {
                            throw new LocalizedException(
                                __('Please choose at least one option for each require extra fee')
                            );
                        }
                    }
                }
            }

            $addresses       = $quote->getAllShippingAddresses();
            $summaryExtraFee = $this->helperData->getMpExtraFee($quote, DisplayArea::CART_SUMMARY);
            $quote->setMpExtraFee(null);
            $summaryExtraFeeOptions = [];
            if (isset($summaryExtraFee['rule'])) {
                foreach ($summaryExtraFee['rule'] as $ruleId => $options) {
                    $rule = $this->ruleFactory->create()->load($ruleId);
                    /** @var  Address $shippingAddress */
                    foreach ($addresses as $shippingAddress) {
                        if ($rule->validate($shippingAddress)) {
                            $summaryExtraFeeOptions[$shippingAddress->getId()][$ruleId] = $options;
                        }
                    }
                }
            }
            /** @var  Address $address */
            foreach ($addresses as $shippingAddress) {
                $area = [DisplayArea::PAYMENT_METHOD, DisplayArea::SHIPPING_METHOD, DisplayArea::CART_SUMMARY];
                if (isset($rules[$shippingAddress->getId()])) {
                    $extraFeePayment = http_build_query(['rule' => $rules[$shippingAddress->getId()]]);
                } else {
                    $extraFeePayment = '';
                    $area            = [DisplayArea::SHIPPING_METHOD, DisplayArea::CART_SUMMARY];
                }
                $this->helperData->setMpExtraFee(
                    $shippingAddress,
                    $extraFeePayment,
                    DisplayArea::PAYMENT_METHOD
                );

                if (isset($summaryExtraFeeOptions[$shippingAddress->getId()])) {
                    $this->helperData->setMpExtraFee(
                        $shippingAddress,
                        http_build_query(['rule' => $summaryExtraFeeOptions[$shippingAddress->getId()]]),
                        DisplayArea::CART_SUMMARY
                    );
                }
                $this->multishippingExtraFee->fetch($shippingAddress, implode(',', $area));
            }

            if ($quote->hasVirtualItems()) {
                $billingAddress                = $quote->getBillingAddress();
                $summaryVirtualExtraFeeOptions = [];
                $areaVirtual                   = [
                    DisplayArea::PAYMENT_METHOD,
                    DisplayArea::SHIPPING_METHOD,
                    DisplayArea::CART_SUMMARY
                ];
                if (isset($summaryExtraFee['rule'])) {
                    foreach ($summaryExtraFee['rule'] as $ruleId => $options) {
                        $rule = $this->ruleFactory->create()->load($ruleId);
                        if ($rule->validate($billingAddress)) {
                            $summaryVirtualExtraFeeOptions[$ruleId] = $options;
                        }
                    }
                }
                if (isset($rules[$billingAddress->getId()])) {
                    $extraFeePaymentForVirtualItems = http_build_query(['rule' => $rules[$billingAddress->getId()]]);
                } else {
                    $extraFeePaymentForVirtualItems = '';
                    $areaVirtual                    = [DisplayArea::CART_SUMMARY];
                }

                $this->helperData->setMpExtraFee(
                    $billingAddress,
                    $extraFeePaymentForVirtualItems,
                    DisplayArea::PAYMENT_METHOD
                );
                $this->helperData->setMpExtraFee(
                    $billingAddress,
                    $extraFeePaymentForVirtualItems,
                    DisplayArea::SHIPPING_METHOD
                );

                if (!empty($summaryVirtualExtraFeeOptions)) {
                    $this->helperData->setMpExtraFee(
                        $billingAddress,
                        http_build_query(['rule' => $summaryVirtualExtraFeeOptions]),
                        DisplayArea::CART_SUMMARY
                    );
                }

                $this->multishippingExtraFee->fetch($billingAddress, implode(',', $areaVirtual));
            }
        }

        return $result;
    }

    /**
     * @param CheckoutMultishipping $subject
     *
     * @throws PaymentProcessingRateLimitExceededException
     */
    public function beforeCreateOrders(CheckoutMultishipping $subject)
    {
        if ($this->helperData->isEnabled($subject->getQuote()->getStoreId())) {
            $quote          = $subject->getQuote();
            $params         = $this->request->getParams();
            $addresses      = $quote->getAllShippingAddresses();
            $billingAddress = $quote->getBillingAddress();
            $ruleRequired   = isset($params['rule_required']) ? $params['rule_required'] : [];
            if ($ruleRequired) {
                $this->validateExtraFeeSummary($billingAddress, $ruleRequired);
                foreach ($addresses as $address) {
                    $this->validateExtraFeeSummary($address, $ruleRequired);
                }
            }
        }
    }

    /**
     * @param Address $address
     * @param array $ruleRequired
     *
     * @throws PaymentProcessingRateLimitExceededException
     */
    protected function validateExtraFeeSummary($address, $ruleRequired)
    {
        $extraFee = $this->helperData->getMpExtraFee($address, DisplayArea::CART_SUMMARY);
        if (isset($ruleRequired[$address->getId()]) && !$extraFee) {
            throw new PaymentProcessingRateLimitExceededException(
                __('Please agree to all Terms and Conditions before placing the order.')
            );
        }
        if (isset($ruleRequired[$address->getId()]) && $extraFee) {
            $ruleIdsRequired = $ruleRequired[$address->getId()];
            $ruleIdsHas      = array_keys($extraFee['rule']);
            $same            = (count($ruleIdsRequired) == count($ruleIdsHas)
                && !array_diff($ruleIdsRequired, $ruleIdsHas));
            if (!$same) {
                throw new PaymentProcessingRateLimitExceededException(
                    __('Please agree to all Terms and Conditions before placing the order.')
                );
            }
        }
    }
}
