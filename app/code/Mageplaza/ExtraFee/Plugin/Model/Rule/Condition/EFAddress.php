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

namespace Mageplaza\ExtraFee\Plugin\Model\Rule\Condition;

use Magento\Framework\Model\AbstractModel;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\QuoteFactory;
use Magento\SalesRule\Model\Rule\Condition\Address;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class EFAddress
 * @package Mageplaza\ExtraFee\Plugin\Model\Rule\Condition
 */
class EFAddress
{
    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * EFAddress constructor.
     *
     * @param Data $helperData
     * @param QuoteFactory $quoteFactory
     */
    public function __construct(
        Data $helperData,
        QuoteFactory $quoteFactory
    ) {
        $this->helperData   = $helperData;
        $this->quoteFactory = $quoteFactory;
    }

    /**
     * @param Address $subject
     * @param $result
     *
     * @return mixed
     */
    public function afterLoadAttributeOptions(Address $subject, $result)
    {
        if ($this->helperData->isEnabled()) {
            $attributes = $result->getAttributeOption();
            if (!array_key_exists('payment_method', $attributes)) {
                $attributes['payment_method'] = __('Payment Method');
                $result->setAttributeOption($attributes);
            }
        }

        return $result;
    }

    /**
     * @param Address $subject
     * @param callable $proceed
     * @param AbstractModel $model
     *
     * @return bool
     */
    public function aroundValidate(Address $subject, callable $proceed, AbstractModel $model)
    {
        $attributeNotValidate = [
            'shipping_method',
            'postcode',
            'region',
            'region_id',
            'country_id',
        ];

        if ($this->helperData->isEnabled() && $model instanceof QuoteAddress
            && $model->getAddressType() === 'billing') {
            $attribute = $subject->getAttribute();
            if (in_array($attribute, $attributeNotValidate, true)) {
                return false;
            }
        }

        if ($this->helperData->isEnabled() && $subject->getAttribute() === 'payment_method') {
            $quote = null;

            // Get quote from model
            if ($model instanceof QuoteAddress) {
                $quote = $model->getQuote();
            } elseif (method_exists($model, 'getQuote')) {
                $quote = $model->getQuote();
            } elseif (method_exists($model, 'getQuoteId') && $model->getQuoteId()) {
                // Fallback: load quote by ID using properly injected factory
                $quote = $this->quoteFactory->create()->load($model->getQuoteId());
            }

            // Set payment method on model for validation
            if ($quote && $quote->getPayment() && $quote->getPayment()->getMethod()) {
                $model->setPaymentMethod($quote->getPayment()->getMethod());
            }
        }

        return $proceed($model);
    }
}
