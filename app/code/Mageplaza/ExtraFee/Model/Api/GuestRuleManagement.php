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

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Mageplaza\ExtraFee\Api\GuestRuleInterface;

/**
 * Class GuestRuleManagement
 * @package Mageplaza\ExtraFee\Model\Api
 */
class GuestRuleManagement implements GuestRuleInterface
{
    /**
     * @var RuleManagement
     */
    protected $ruleManagement;

    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * GuestRuleManagement constructor.
     *
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param RuleManagement $ruleManagement
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        RuleManagement $ruleManagement
    ) {
        $this->ruleManagement     = $ruleManagement;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchEntityException
     */
    public function update($cartId, $area, ShippingInformationInterface $addressInformation)
    {
        /** @var $quoteIdMask QuoteIdMask */
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');

        return $this->ruleManagement->update($quoteIdMask->getQuoteId(), $area, $addressInformation);
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getRules($cartId, $area, ShippingInformationInterface $addressInformation)
    {
        /** @var $quoteIdMask QuoteIdMask */
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');

        return $this->ruleManagement->getRules($quoteIdMask->getQuoteId(), $area, $addressInformation);
    }

    /**
     * {@inheritdoc}
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function collectTotal($cartId, $formData, $area)
    {
        /** @var $quoteIdMask QuoteIdMask */
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');

        return $this->ruleManagement->collectTotal($quoteIdMask->getQuoteId(), $formData, $area);
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
