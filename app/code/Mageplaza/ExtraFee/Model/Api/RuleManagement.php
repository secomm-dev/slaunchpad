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

namespace Mageplaza\ExtraFee\Model\Api;

use Exception;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\TotalsInterface;
use Magento\Quote\Model\Quote;
use Mageplaza\ExtraFee\Api\RuleInterface;
use Mageplaza\ExtraFee\Helper\Data as HelperData;
use Mageplaza\ExtraFee\Model\Config\Source\DisplayArea;
use Mageplaza\ExtraFee\Model\Multishipping\ExtraFee;

/**
 * Class RuleManagement
 * @package Mageplaza\ExtraFee\Model\Api
 */
class RuleManagement implements RuleInterface
{
    /**
     * @var HelperData
     */
    protected $helperData;

    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * Cart total repository.
     *
     * @var CartTotalRepositoryInterface
     */
    protected $cartTotalsRepository;

    /**
     * @var ExtraFee
     */
    protected $multiShippingExtraFee;

    /**
     * RuleManagement constructor.
     *
     * @param HelperData $helperData
     * @param CartRepositoryInterface $cartRepository
     * @param CartTotalRepositoryInterface $cartTotalsRepository
     * @param ExtraFee $multiShippingExtraFee
     */
    public function __construct(
        HelperData $helperData,
        CartRepositoryInterface $cartRepository,
        CartTotalRepositoryInterface $cartTotalsRepository,
        ExtraFee $multiShippingExtraFee
    ) {
        $this->helperData            = $helperData;
        $this->cartRepository        = $cartRepository;
        $this->cartTotalsRepository  = $cartTotalsRepository;
        $this->multiShippingExtraFee = $multiShippingExtraFee;
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getRules($cartId, $area, ShippingInformationInterface $addressInformation)
    {
        if (!$this->helperData->isEnabled()) {
            throw new LocalizedException(__('Module is disabled.'));
        }

        if (!$area) {
            throw new LocalizedException(__('area is required.'));
        }

        $mpExtraFees   = $this->update($cartId, $area, $addressInformation);
        $extraFeeRules = [];

        if (count($mpExtraFees[0]) > 0) {
            foreach ($mpExtraFees[0] as $rules) {
                foreach ($rules as $rule) {
                    $type = '';
                    $area = (int) $rule['area'];
                    if ($area === DisplayArea::PAYMENT_METHOD) {
                        $type = 'payment_method';
                    } elseif ($area === DisplayArea::SHIPPING_METHOD) {
                        $type = 'shipping_method';
                    } elseif ($area === DisplayArea::CART_SUMMARY) {
                        $type = 'cart_summary';
                    }

                    if ($type) {
                        $extraFeeRules[$type][] = $rule;
                    }
                }
            }
        }

        $selectedOptions = [];

        if (count($mpExtraFees[1]) > 0) {
            foreach ($mpExtraFees[1] as $selectedOption) {
                if (isset($selectedOption['rule'])) {
                    foreach ($selectedOption['rule'] as $ruleId => $rule) {
                        $selectedOptions[$ruleId] = $rule;
                    }
                }
            }
        }
        $rules     = new DataObject($extraFeeRules);
        $areaRules = new DataObject($extraFeeRules);
        $rules->setData('rules', $areaRules);

        if ($selectedOptions) {
            $rules->setData('selected_options', HelperData::jsonEncode($selectedOptions));
        }

        return $rules;
    }

    /**
     * {@inheritdoc}
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function update($cartId, $area, ShippingInformationInterface $addressInformation)
    {
        /** @var Quote $quote */
        $quote = $this->cartRepository->get($cartId);

        if ($addressInformation->getBillingAddress()) {
            $quote->setBillingAddress($addressInformation->getBillingAddress());
        }

        if ($addressInformation->getShippingAddress()) {
            $quote->setShippingAddress($addressInformation->getShippingAddress());
        }

        /** @var Session $checkoutSession */
        $checkoutSession = $this->helperData->getCheckoutSession();

        if ($addressInformation->getExtensionAttributes()) {
            $extensionAttributes = $addressInformation->getExtensionAttributes();
            if ($extensionAttributes && $extensionAttributes->getMpEfPaymentMethod()) {
                $quote->getPayment()->setMethod($extensionAttributes->getMpEfPaymentMethod());
                $this->helperData->clearValidationCacheForQuote($quote);
            }
        }

        $checkoutSession->setMpArea($area);
        $quote->collectTotals();

        return $checkoutSession->getMpExtraFee() ?: [[], []];
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function collectTotal($cartId, $formData, $area)
    {
        /** @var Quote $quote */
        $quote         = $this->cartRepository->getActive($cartId);
        $areaArray     = explode(',', $area);
        $formDataArray = explode(',', $formData);

        foreach ($areaArray as $key => $item) {
            $this->helperData->setMpExtraFee($quote, $formDataArray[$key], $item);
        }

        try {
            $quote->collectTotals();
            $this->cartRepository->save($quote);
        } catch (Exception $e) {
            throw new CouldNotSaveException(__('Could not add extra fee for this quote'));
        }

        return $this->getResponseData($quote);
    }

    /**
     * @param Quote $quote
     *
     * @return TotalsInterface
     * @throws NoSuchEntityException
     */
    public function getResponseData(Quote $quote)
    {
        return $this->cartTotalsRepository->get($quote->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function updateMultiShipping($cartId, $area, $shippingMethods)
    {
        /** @var Quote $quote */
        $quote                  = $this->cartRepository->get($cartId);
        $addresses              = $quote->getAllShippingAddresses();
        $rules                  = [];
        $shippingMethods        = HelperData::jsonDecode($shippingMethods);
        $selectedOptions        = [];
        $rulesVirtual           = [];
        $selectedOptionsVirtual = [];
        $billingAddress         = $quote->getBillingAddress();

        foreach ($addresses as $address) {
            $addressId = $address->getId();
            if ((int) $area === DisplayArea::SHIPPING_METHOD) {
                if (isset($shippingMethods[$addressId])) {
                    $address->setShippingMethod($shippingMethods[$addressId]);
                    $address->setCollectShippingRates(true);
                }
            }
        }

        $quote->setTotalsCollectedFlag(false)->collectTotals();
        $this->cartRepository->save($quote);

        foreach ($addresses as $address) {
            $addressId = $address->getId();
            $this->multiShippingExtraFee->fetch($address, $area);
            $rules[$addressId] = $this->multiShippingExtraFee->getAllApplyRule($address, $area);
            [$applyRule, $selectedOption] = $this->multiShippingExtraFee->getApplyRule($address, $area);
            [$applyRule, $selectedOption] = [$applyRule[0], $selectedOption[0]];
            $selectedOptions[$addressId] = $selectedOption;
        }

        if ((int) $area === DisplayArea::PAYMENT_METHOD
            && $quote->hasVirtualItems() && count($billingAddress->getAllVisibleItems())) {
            $rulesVirtual[$billingAddress->getId()] =
                array_merge(
                    $this->multiShippingExtraFee->getAllApplyRule($billingAddress, DisplayArea::PAYMENT_METHOD),
                    $this->multiShippingExtraFee->getAllApplyRule($billingAddress, DisplayArea::SHIPPING_METHOD)
                );
            $area                                   = [DisplayArea::SHIPPING_METHOD, DisplayArea::PAYMENT_METHOD];
            [$applyRule, $selectedOption] = $this->multiShippingExtraFee->getApplyRule(
                $billingAddress,
                implode(',', $area)
            );
            foreach ($selectedOption as $select) {
                foreach ($select as $sl) {
                    foreach ($sl as $k => $s) {
                        $selectedOptionsVirtual[$billingAddress->getId()]['rule'][$k] = $s;
                    }
                }
            }
        }

        return [$rules, $selectedOptions, $rulesVirtual, $selectedOptionsVirtual];
    }

    /**
     * @param Quote $quote
     *
     * @return void
     * @throws LocalizedException
     */
    protected function validateQuote(Quote $quote)
    {
        if ($quote->getItemsCount() === 0) {
            throw new LocalizedException(
                __('Totals calculation is not applicable to empty cart.')
            );
        }
    }
}
