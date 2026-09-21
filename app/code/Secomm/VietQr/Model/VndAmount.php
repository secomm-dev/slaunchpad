<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\VietQr\Model;

use Magento\Directory\Model\CurrencyFactory;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Resolves an order amount in VND — the only currency a Vietnamese bank
 * transfer (and therefore the VietQR API) accepts (BUG-4BX0CK / SLP-234).
 *
 * Resolution order: order currency is VND → grand total; base currency is
 * VND → base grand total (no rate lookup needed); otherwise convert the
 * grand total via the configured directory currency rate. When no VND rate
 * is configured the conversion throws — QR generation then fails and the
 * customer falls back to manual bank transfer instructions instead of a QR
 * with a wrong amount.
 *
 * VND has no subunit — every amount is rounded to an integer.
 */
class VndAmount
{
    private const CURRENCY_CODE = 'VND';

    public function __construct(
        private readonly CurrencyFactory $currencyFactory
    ) {
    }

    /**
     * Order grand total converted to VND, rounded to an integer.
     *
     * @param OrderInterface $order
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException When no VND currency rate is configured
     */
    public function get(OrderInterface $order): int
    {
        $amount = $order->getOrderCurrencyCode() === self::CURRENCY_CODE
            ? (float)$order->getGrandTotal()
            : ($order->getBaseCurrencyCode() === self::CURRENCY_CODE
                ? (float)$order->getBaseGrandTotal()
                : $this->convert((float)$order->getGrandTotal(), (string)$order->getOrderCurrencyCode()));

        return (int)round($amount);
    }

    /**
     * Format a VND amount for display (VND has no decimals).
     *
     * @param int|float $amount
     * @return string
     */
    public function format(int|float $amount): string
    {
        return number_format((float)$amount, 0, ',', '.') . ' ' . self::CURRENCY_CODE;
    }

    /**
     * @param float $amount
     * @param string $fromCurrencyCode
     * @return float
     * @throws \Magento\Framework\Exception\LocalizedException When no rate is configured
     */
    private function convert(float $amount, string $fromCurrencyCode): float
    {
        return (float)$this->currencyFactory->create()
            ->load($fromCurrencyCode)
            ->convert($amount, self::CURRENCY_CODE);
    }
}
