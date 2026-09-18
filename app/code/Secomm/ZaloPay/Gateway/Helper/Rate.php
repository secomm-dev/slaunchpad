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
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
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
     * VND amount (VND is zero-decimal in the provider protocol). Accepts
     * both a native Sales Order and the gateway OrderAdapter: the gateway
     * command path (RefundCommand::readVndAmount) passes the OrderAdapter,
     * which is NOT a Sales Order subclass — type-hinting the native Order
     * made every synchronous refund die on a TypeError before the provider
     * was ever asked (round 7, REAL-Magento smoke).
     *
     * @param Order|OrderAdapterInterface $order
     * @param mixed $amount
     * @return float
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getVndAmount($order, $amount): float
    {
        $numericAmount = $this->toNumericAmount($amount);
        if ($this->isVietnamDong($order)) {
            return round($numericAmount);
        } else {
            try {
                return round($this->helperData->currencyConvert(
                    $numericAmount,
                    $this->resolveOrderCurrencyCode($order),
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
        $numericAmount = $this->toNumericAmount($amount);
        if ($currency === self::CURRENCY_CODE) {
            return round($numericAmount);
        } else {
            try {
                return round($this->helperData->currencyConvert(
                    $numericAmount,
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
     * Strict money-amount parse (TASK-CG6BM7): accepts ints, floats and
     * well-formed numeric strings — plain decimals ("1685000.0000") and
     * thousands-grouped decimals ("1,685,000.00"). Malformed input ("abc",
     * "1,2,3", "10foo", arrays, objects, booleans, null) is REJECTED with a
     * LocalizedException — malformed money is never silently coerced to
     * zero.
     *
     * @param mixed $amount
     * @return float
     * @throws LocalizedException
     */
    private function toNumericAmount($amount): float
    {
        if (is_int($amount) || is_float($amount)) {
            return (float)$amount;
        }
        if (is_string($amount)) {
            $candidate = trim($amount);
            if (preg_match('/^-?\d+(\.\d+)?$/', $candidate) === 1) {
                return (float)$candidate;
            }
            if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $candidate) === 1) {
                return (float)str_replace(',', '', $candidate);
            }
        }

        throw new LocalizedException(
            __(
                'Invalid payment amount: a well-formed numeric amount is required (given %1).',
                is_scalar($amount) ? var_export($amount, true) : get_debug_type($amount)
            )
        );
    }

    /**
     * Currency of the order as presented to the gateway. The native Sales
     * Order exposes getOrderCurrencyCode(); the gateway OrderAdapter
     * (module-payment) exposes getCurrencyCode() — they differ, and both
     * shapes reach this helper from the refund path (round 7 REAL smoke).
     *
     * @param Order|OrderAdapterInterface $order
     * @return string|null
     */
    private function resolveOrderCurrencyCode($order): ?string
    {
        if ($order instanceof OrderAdapterInterface) {
            return $order->getCurrencyCode();
        }

        return $order->getOrderCurrencyCode();
    }

    /**
     * @param Order|OrderAdapterInterface $order
     * @return boolean
     */
    private function isVietnamDong($order): bool
    {
        return $this->resolveOrderCurrencyCode($order) === self::CURRENCY_CODE;
    }
}
