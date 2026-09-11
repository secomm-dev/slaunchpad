<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   VNPay
 */

namespace Secomm\VNPAY\Helper;

use Magento\Directory\Helper\Data;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

class Rate
{
    /**
     * Vietnam dong currency
     */
    public const CURRENCY_CODE = 'VND';

    public function __construct(
        private readonly Data $helperData
    ) {
    }

    /**
     * Convert the given amount to VND based on the order currency.
     *
     * @param Order $order
     * @param float $amount
     * @return float
     * @throws LocalizedException
     */
    public function getVndAmount(Order $order, float $amount): float
    {
        if ($this->isVietnamDong($order)) {
            return round($amount);
        }

        try {
            return round($this->helperData->currencyConvert(
                $amount,
                $order->getOrderCurrencyCode(),
                self::CURRENCY_CODE
            ));
        } catch (\Exception) {
            throw new LocalizedException(
                __('We can\'t convert base currency to %1. Please setup currency rates.', self::CURRENCY_CODE)
            );
        }
    }

    /**
     * Convert the given amount to VND for the given currency.
     *
     * @param string $currency
     * @param float $amount
     * @return float
     * @throws LocalizedException
     */
    public function getVndAmountByCurrency(string $currency, float $amount): float
    {
        if ($currency === self::CURRENCY_CODE) {
            return round($amount);
        }

        try {
            return round($this->helperData->currencyConvert(
                $amount,
                $currency,
                self::CURRENCY_CODE
            ));
        } catch (\Exception) {
            throw new LocalizedException(
                __('We can\'t convert base currency to %1. Please setup currency rates.', self::CURRENCY_CODE)
            );
        }
    }

    /**
     * Whether the order currency is VND
     */
    private function isVietnamDong(Order $order): bool
    {
        return $order->getOrderCurrencyCode() === self::CURRENCY_CODE;
    }
}