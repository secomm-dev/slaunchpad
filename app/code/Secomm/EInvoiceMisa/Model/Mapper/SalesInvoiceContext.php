<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Mapper;

use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\StateException;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Payment;

/**
 * Resolves MeInvoice InvoiceData currency, exchange rate and payment method from Magento sales entities.
 */
class SalesInvoiceContext
{
    public const CURRENCY_VND = 'VND';

    /**
     * @param CurrencyFactory $currencyFactory
     */
    public function __construct(
        private readonly CurrencyFactory $currencyFactory
    ) {
    }

    /**
     * Order currency code placed by the customer (transaction currency).
     *
     * @param OrderInterface $order
     * @return string
     */
    public function getCurrencyCode(OrderInterface $order): string
    {
        $code = (string) ($order->getOrderCurrencyCode() ?: $order->getBaseCurrencyCode());

        return $code !== '' ? $code : self::CURRENCY_VND;
    }

    /**
     * MeInvoice ExchangeRate: transaction currency → VND (1 when CurrencyCode is VND).
     *
     * @param OrderInterface $order
     * @return float
     * @throws LocalizedException When foreign currency has no VND rate in Magento Currency Rates.
     */
    public function getExchangeRate(OrderInterface $order): float
    {
        return $this->resolveExchangeRateToVnd($this->getCurrencyCode($order));
    }

    /**
     * Convert an OC (transaction currency) amount to VND using MeInvoice exchange rate.
     *
     * @param float $amountOc Amount in order/transaction currency.
     * @param float $exchangeRate Rate to VND (1 for VND orders).
     * @return float Rounded VND amount.
     */
    public function convertOcToVnd(float $amountOc, float $exchangeRate): float
    {
        if ($exchangeRate <= 1.0) {
            // VND orders: MeInvoice requires AmountOC === Amount (no separate rounding).
            return $amountOc;
        }

        return round($amountOc * $exchangeRate, 0);
    }

    /**
     * Payment method label from the order payment (admin title).
     *
     * @param OrderInterface $order
     * @return string
     */
    public function getPaymentMethodName(OrderInterface $order): string
    {
        $payment = $order->getPayment();
        if (!$payment instanceof Payment) {
            return '';
        }

        $methodCode = (string) $payment->getMethod();
        if ($methodCode === '') {
            return '';
        }

        try {
            $title = trim((string) $payment->getMethodInstance()->getTitle());
            if ($title !== '') {
                return $title;
            }
        } catch (LocalizedException | StateException) {
            // Payment method unavailable (disabled module, etc.)
        }

        return $methodCode;
    }

    /**
     * Currency code for adjustment invoices from credit memo / linked order.
     *
     * @param CreditmemoInterface $creditmemo
     * @param OrderInterface|null $order
     * @return string
     */
    public function getCurrencyCodeFromCreditmemo(CreditmemoInterface $creditmemo, ?OrderInterface $order): string
    {
        $code = (string) ($creditmemo->getOrderCurrencyCode()
            ?: $order?->getOrderCurrencyCode()
            ?: $order?->getBaseCurrencyCode());

        return $code !== '' ? $code : self::CURRENCY_VND;
    }

    /**
     * Exchange rate for adjustment invoices (transaction currency → VND).
     *
     * @param CreditmemoInterface $creditmemo
     * @param OrderInterface|null $order
     * @return float
     * @throws LocalizedException When foreign currency has no VND rate in Magento Currency Rates.
     */
    public function getExchangeRateFromCreditmemo(CreditmemoInterface $creditmemo, ?OrderInterface $order): float
    {
        return $this->resolveExchangeRateToVnd(
            $this->getCurrencyCodeFromCreditmemo($creditmemo, $order)
        );
    }

    /**
     * Payment method label for adjustment invoices (from the original order).
     *
     * @param OrderInterface|null $order
     * @return string
     */
    public function getPaymentMethodNameFromCreditmemo(?OrderInterface $order): string
    {
        return $order !== null ? $this->getPaymentMethodName($order) : '';
    }

    /**
     * Resolve Magento directory rate from transaction currency to VND.
     *
     * @param string $currencyCode
     * @return float
     * @throws LocalizedException
     */
    private function resolveExchangeRateToVnd(string $currencyCode): float
    {
        $currencyCode = strtoupper(trim($currencyCode));

        if ($currencyCode === '' || $currencyCode === self::CURRENCY_VND) {
            return 1.0;
        }

        $rate = (float) $this->currencyFactory->create()->load($currencyCode)->getAnyRate(self::CURRENCY_VND);

        if ($rate <= 0) {
            throw new LocalizedException(__(
                'Missing currency rate from %1 to %2. Configure it under Stores → Currency → Currency Rates.',
                $currencyCode,
                self::CURRENCY_VND
            ));
        }

        return $rate;
    }
}
