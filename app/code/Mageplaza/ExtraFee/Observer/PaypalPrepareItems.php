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

namespace Mageplaza\ExtraFee\Observer;

use Magento\Checkout\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Model\Cart;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class PaypalPrepareItems
 * @package Mageplaza\ExtraFee\Observer
 */
class PaypalPrepareItems implements ObserverInterface
{
    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * PaypalPrepareItems constructor.
     *
     * @param Session $checkoutSession
     * @param Data $helperData
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Session $checkoutSession,
        Data $helperData,
        StoreManagerInterface $storeManager
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->helperData      = $helperData;
        $this->storeManager    = $storeManager;
    }

    /**
     * @param Observer $observer
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        /** @var Cart $cart */
        $cart      = $observer->getEvent()->getCart();
        $extraFees = $this->helperData->getExtraFeeTotals($this->checkoutSession->getQuote());

        if (!empty($extraFees)) {
            $total = 0;
            $qty   = 0;
            foreach ($extraFees as $extraFee) {
                if (is_array($extraFee)) {
                    $qty++;
                    $total += $extraFee['value_excl_tax'];
                }
            }
            if ($qty > 0) {
                if (!$this->isSameCurrency($this->checkoutSession->getQuote())) {
                    $total = $this->convertPrice($total);
                }
                $cart->addCustomItem(__('Extra Fee'), 1, $total);
            }
        }
    }

    /**
     * @param Quote $quote
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isSameCurrency($quote)
    {
        $currentCurrencyCode = $this->storeManager->getStore()->getCurrentCurrency()->getCode();
        $baseCurrencyCode    = $quote->getBaseCurrencyCode();

        return $currentCurrencyCode === $baseCurrencyCode;
    }

    /**
     * @param float $amount
     *
     * @return false|float|int
     */
    public function convertPrice($amount)
    {
        try {
            $currentCurrency = $this->storeManager->getStore()->getCurrentCurrency();
            $rate            = $this->storeManager->getStore()->getBaseCurrency()->getRate($currentCurrency);

            return round($amount / $rate, 2);
        } catch (LocalizedException $e) {
            return 0;
        }
    }
}
