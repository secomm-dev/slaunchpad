<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Issuer;

use Magento\Framework\Exception\LocalizedException;

/**
 * Ensures MeInvoice InvoiceData header totals match InvoiceDetail (+ FeeInfo / discount).
 */
class InvoicePayloadTotalsValidator
{
    private const TOLERANCE = 0.01;

    /**
     * @param array<string, mixed> $payload
     * @throws LocalizedException
     */
    public function assertConsistent(array $payload): void
    {
        $this->assertHeaderTotals($payload);
        $this->assertDetailLinesMatchHeader($payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @throws LocalizedException
     */
    private function assertHeaderTotals(array $payload): void
    {
        $totalOc = (float) ($payload['TotalAmountOC'] ?? 0);
        $exclVatOc = (float) ($payload['TotalAmountWithoutVATOC'] ?? 0);
        $vatOc = (float) ($payload['TotalVATAmountOC'] ?? 0);

        if (abs($totalOc - ($exclVatOc + $vatOc)) >= self::TOLERANCE) {
            throw new LocalizedException(__(
                'Invoice TotalAmountOC (%1) must equal TotalAmountWithoutVATOC (%2) + TotalVATAmountOC (%3).',
                $totalOc,
                $exclVatOc,
                $vatOc
            ));
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @throws LocalizedException
     */
    private function assertDetailLinesMatchHeader(array $payload): void
    {
        $lineExclVatOc = 0.0;
        $lineVatOc = 0.0;

        foreach ($payload['InvoiceDetail'] ?? [] as $line) {
            if (!is_array($line)) {
                continue;
            }
            if ((int) ($line['ItemType'] ?? 1) === 4) {
                continue;
            }

            $lineExclVatOc += (float) ($line['AmountWithoutVATOC'] ?? $line['AmountOC'] ?? 0);
            $lineVatOc += (float) ($line['VATAmountOC'] ?? 0);
        }

        $feeOc = 0.0;
        foreach ($payload['FeeInfo'] ?? [] as $fee) {
            if (!is_array($fee)) {
                continue;
            }
            $feeOc += (float) ($fee['FeeAmountOC'] ?? 0);
        }

        $discountOc = (float) ($payload['TotalDiscountAmountOC'] ?? 0);
        $expectedExclVatOc = (float) ($payload['TotalAmountWithoutVATOC'] ?? 0);
        $expectedVatOc = (float) ($payload['TotalVATAmountOC'] ?? 0);

        $computedExclVatOc = $lineExclVatOc - $discountOc + $feeOc;
        $exclGap = round($computedExclVatOc - $expectedExclVatOc, 2);
        $vatGap = round($lineVatOc - $expectedVatOc, 2);

        if (abs($exclGap) >= self::TOLERANCE) {
            if (abs($feeOc) >= self::TOLERANCE && abs($lineExclVatOc - $discountOc - $expectedExclVatOc) >= self::TOLERANCE) {
                throw new LocalizedException(__(
                    'Invoice lines (excl. VAT: %1) minus discount (%2) do not match TotalAmountWithoutVATOC (%3). '
                    . 'FeeInfo (%4) must not patch large differences — add explicit InvoiceDetail lines instead.',
                    $lineExclVatOc,
                    $discountOc,
                    $expectedExclVatOc,
                    $feeOc
                ));
            }

            throw new LocalizedException(__(
                'Invoice lines (excl. VAT: %1) minus discount (%2) plus FeeInfo (%3) '
                . 'do not match TotalAmountWithoutVATOC (%4).',
                $lineExclVatOc,
                $discountOc,
                $feeOc,
                $expectedExclVatOc
            ));
        }

        if (abs($vatGap) >= self::TOLERANCE) {
            throw new LocalizedException(__(
                'Invoice line VAT total (%1) does not match TotalVATAmountOC (%2).',
                $lineVatOc,
                $expectedVatOc
            ));
        }
    }
}
