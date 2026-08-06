<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Gateway\Helper;

use Magento\Directory\Helper\Data;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;

class Rate
{
    /**
     * Vietnam dong currency
     */
    const CURRENCY_CODE = 'VND';

    /**
     * OrderDetailsDataBuilder constructor.
     *
     * @param Data $helperData
     */
    public function __construct(
        private readonly Data $helperData,
    ) {
    }

    /**
     * @param Order $order
     * @param $amount
     * @return float
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getVndAmount(Order $order, $amount): float
    {
        if ($this->isVietnamDong($order)) {
            return round($amount);
        } else {
            try {
                return round($this->helperData->currencyConvert(
                    $amount,
                    $order->getOrderCurrencyCode(),
                    self::CURRENCY_CODE
                ));
            } catch (\Exception $e) {
                throw new LocalizedException(
                    __('We can\'t convert base currency to %1. Please setup currency rates.', self::CURRENCY_CODE)
                );
            }
        }
    }

    /**
     * @param $currency
     * @param $amount
     * @return float
     * @throws LocalizedException
     */
    public function getVndAmountByCurrency($currency, $amount): float
    {
        if ($currency === self::CURRENCY_CODE) {
            return round($amount);
        } else {
            try {
                return round($this->helperData->currencyConvert(
                    $amount,
                    $currency,
                    self::CURRENCY_CODE
                ));
            } catch (\Exception $e) {
                throw new LocalizedException(
                    __('We can\'t convert base currency to %1. Please setup currency rates.', self::CURRENCY_CODE)
                );
            }
        }
    }

    /**
     * @param Order $order
     * @return boolean
     */
    private function isVietnamDong(Order $order): bool
    {
        return $order->getOrderCurrencyCode() === self::CURRENCY_CODE;
    }
}
