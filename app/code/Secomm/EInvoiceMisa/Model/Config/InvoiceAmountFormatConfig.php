<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Secomm\EInvoiceMisa\Model\Mapper\SalesInvoiceContext;

/**
 * MeInvoice OptionUserDefined (API §15.9) — decimal display on published invoices.
 */
class InvoiceAmountFormatConfig
{
    public const XML_PATH_AMOUNT_DECIMAL_DIGITS = 'secomm_einvoice/amount_format/amount_decimal_digits';
    public const XML_PATH_AMOUNT_OC_DECIMAL_DIGITS = 'secomm_einvoice/amount_format/amount_oc_decimal_digits';
    public const XML_PATH_UNIT_PRICE_OC_DECIMAL_DIGITS = 'secomm_einvoice/amount_format/unit_price_oc_decimal_digits';
    public const XML_PATH_UNIT_PRICE_DECIMAL_DIGITS = 'secomm_einvoice/amount_format/unit_price_decimal_digits';
    public const XML_PATH_QUANTITY_DECIMAL_DIGITS = 'secomm_einvoice/amount_format/quantity_decimal_digits';
    public const XML_PATH_COEFFICIENT_DECIMAL_DIGITS = 'secomm_einvoice/amount_format/coefficient_decimal_digits';
    public const XML_PATH_EXCHANGE_RATE_DECIMAL_DIGITS = 'secomm_einvoice/amount_format/exchange_rate_decimal_digits';
    public const XML_PATH_CLOCK_DECIMAL_DIGITS = 'secomm_einvoice/amount_format/clock_decimal_digits';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function getOptionUserDefined(?int $storeId = null): array
    {
        return [
            'MainCurrency' => SalesInvoiceContext::CURRENCY_VND,
            'AmountDecimalDigits' => $this->getDigits(self::XML_PATH_AMOUNT_DECIMAL_DIGITS, '0', $storeId),
            'AmountOCDecimalDigits' => $this->getDigits(self::XML_PATH_AMOUNT_OC_DECIMAL_DIGITS, '0', $storeId),
            'UnitPriceOCDecimalDigits' => $this->getDigits(self::XML_PATH_UNIT_PRICE_OC_DECIMAL_DIGITS, '2', $storeId),
            'UnitPriceDecimalDigits' => $this->getDigits(self::XML_PATH_UNIT_PRICE_DECIMAL_DIGITS, '2', $storeId),
            'QuantityDecimalDigits' => $this->getDigits(self::XML_PATH_QUANTITY_DECIMAL_DIGITS, '2', $storeId),
            'CoefficientDecimalDigits' => $this->getDigits(self::XML_PATH_COEFFICIENT_DECIMAL_DIGITS, '2', $storeId),
            'ExchangRateDecimalDigits' => $this->getDigits(self::XML_PATH_EXCHANGE_RATE_DECIMAL_DIGITS, '0', $storeId),
            'ClockDecimalDigits' => $this->getDigits(self::XML_PATH_CLOCK_DECIMAL_DIGITS, '2', $storeId),
        ];
    }

    private function getDigits(string $path, string $default, ?int $storeId): string
    {
        $value = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);

        return $value !== null && $value !== '' ? (string) $value : $default;
    }
}
