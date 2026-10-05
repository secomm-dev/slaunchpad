<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Test\Unit\Model\Issuer;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Secomm\EInvoiceMisa\Model\Issuer\InvoicePayloadTotalsValidator;

class InvoicePayloadTotalsValidatorTest extends TestCase
{
    private InvoicePayloadTotalsValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new InvoicePayloadTotalsValidator();
    }

    public function testAcceptsBalancedCreditMemoStylePayload(): void
    {
        $this->validator->assertConsistent([
            'TotalAmountOC' => 29.0,
            'TotalAmountWithoutVATOC' => 29.0,
            'TotalVATAmountOC' => 0.0,
            'TotalDiscountAmountOC' => 0.0,
            'InvoiceDetail' => [
                ['ItemType' => 1, 'AmountWithoutVATOC' => 34.0, 'VATAmountOC' => 0.0],
                ['ItemType' => 1, 'AmountWithoutVATOC' => 5.0, 'VATAmountOC' => 0.0],
                ['ItemType' => 1, 'AmountWithoutVATOC' => -10.0, 'VATAmountOC' => 0.0],
            ],
            'FeeInfo' => [],
        ]);

        $this->addToAssertionCount(1);
    }

    public function testRejectsOrder000000004StylePayloadWithFeeInfoPatch(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('FeeInfo');

        $this->validator->assertConsistent([
            'TotalAmountOC' => 29.0,
            'TotalAmountWithoutVATOC' => 29.0,
            'TotalVATAmountOC' => 0.0,
            'TotalDiscountAmountOC' => 0.0,
            'InvoiceDetail' => [
                ['ItemType' => 1, 'AmountWithoutVATOC' => 5.0, 'VATAmountOC' => 0.0],
            ],
            'FeeInfo' => [
                ['FeeName' => 'Điều chỉnh', 'FeeAmountOC' => 24.0],
            ],
        ]);
    }

    public function testRejectsHeaderTotalMismatch(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('TotalAmountOC');

        $this->validator->assertConsistent([
            'TotalAmountOC' => 30.0,
            'TotalAmountWithoutVATOC' => 29.0,
            'TotalVATAmountOC' => 0.0,
            'InvoiceDetail' => [],
            'FeeInfo' => [],
        ]);
    }

    public function testAcceptsOrderPayloadWithDiscountAndSmallFeeInfoRounding(): void
    {
        $this->validator->assertConsistent([
            'TotalAmountOC' => 115.0,
            'TotalAmountWithoutVATOC' => 104.0,
            'TotalVATAmountOC' => 11.0,
            'TotalDiscountAmountOC' => 5.0,
            'InvoiceDetail' => [
                ['ItemType' => 1, 'AmountWithoutVATOC' => 100.0, 'VATAmountOC' => 10.0],
                ['ItemType' => 1, 'AmountWithoutVATOC' => 10.0, 'VATAmountOC' => 1.0],
            ],
            'FeeInfo' => [
                ['FeeName' => 'Điều chỉnh', 'FeeAmountOC' => -1.0],
            ],
        ]);

        $this->addToAssertionCount(1);
    }

    public function testAllowsSubCentFeeInfoRounding(): void
    {
        $this->validator->assertConsistent([
            'TotalAmountOC' => 100.01,
            'TotalAmountWithoutVATOC' => 100.01,
            'TotalVATAmountOC' => 0.0,
            'TotalDiscountAmountOC' => 0.0,
            'InvoiceDetail' => [
                ['ItemType' => 1, 'AmountWithoutVATOC' => 100.0, 'VATAmountOC' => 0.0],
            ],
            'FeeInfo' => [
                ['FeeName' => 'Điều chỉnh', 'FeeAmountOC' => 0.01],
            ],
        ]);

        $this->addToAssertionCount(1);
    }
}
