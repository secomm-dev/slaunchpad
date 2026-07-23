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
 * @package     Mageplaza_AbandonedCart
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\AbandonedCart\Plugin;

use Exception;
use Magento\Customer\Model\AccountManagement as CustomerAccountManagement;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Mageplaza\AbandonedCart\Helper\Data;

/**
 * Class SaveCustomerEmail
 * @package Mageplaza\AbandonedCart\Plugin
 */
class SaveCustomerEmail
{
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * SaveCustomerEmail constructor.
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $cartRepository
     * @param Data $helperData
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $cartRepository,
        Data $helperData
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository  = $cartRepository;
        $this->helperData      = $helperData;
    }

    /**
     * @param CustomerAccountManagement $subject
     * @param $result
     * @param $customerEmail
     *
     * @return false|mixed
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function afterIsEmailAvailable(CustomerAccountManagement $subject, $result, $customerEmail)
    {
        if (!$this->helperData->isEnabled() ||
            $this->helperData->isModuleOutputEnabled('Mageplaza_Osc')) {

            return $result;
        }

        $quote = $this->checkoutSession->getQuote();

        $cartId = $quote->getId();

        if (!$cartId || $quote->getCustomerEmail() === $customerEmail) {
            return $result;
        }

        /** @var Quote $quote */
        $quote = $this->cartRepository->getActive($cartId);
        $quote->setCustomerEmail($customerEmail);

        try {
            $this->cartRepository->save($quote);

            return $result;
        } catch (Exception $e) {
            return false;
        }
    }
}
