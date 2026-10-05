<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Test\Unit\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\EInvoiceMisa\Model\Config\InvoiceAmountFormatConfig;

class InvoiceAmountFormatConfigTest extends TestCase
{
    public function testGetOptionUserDefinedUsesDefaults(): void
    {
        $scopeConfig = $this->createScopeConfig([]);
        $config = new InvoiceAmountFormatConfig($scopeConfig);

        self::assertSame([
            'MainCurrency' => 'VND',
            'AmountDecimalDigits' => '0',
            'AmountOCDecimalDigits' => '0',
            'UnitPriceOCDecimalDigits' => '2',
            'UnitPriceDecimalDigits' => '2',
            'QuantityDecimalDigits' => '2',
            'CoefficientDecimalDigits' => '2',
            'ExchangRateDecimalDigits' => '0',
            'ClockDecimalDigits' => '2',
        ], $config->getOptionUserDefined(1));
    }

    public function testGetOptionUserDefinedReadsStoreScope(): void
    {
        $scopeConfig = $this->createScopeConfig([
            InvoiceAmountFormatConfig::XML_PATH_AMOUNT_DECIMAL_DIGITS => '2',
            InvoiceAmountFormatConfig::XML_PATH_AMOUNT_OC_DECIMAL_DIGITS => '2',
        ]);
        $config = new InvoiceAmountFormatConfig($scopeConfig);

        $option = $config->getOptionUserDefined(1);

        self::assertSame('2', $option['AmountDecimalDigits']);
        self::assertSame('2', $option['AmountOCDecimalDigits']);
    }

    /**
     * @param array<string, string> $values
     */
    private function createScopeConfig(array $values): ScopeConfigInterface&MockObject
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            function (string $path) use ($values): ?string {
                return $values[$path] ?? null;
            }
        );

        return $scopeConfig;
    }
}
