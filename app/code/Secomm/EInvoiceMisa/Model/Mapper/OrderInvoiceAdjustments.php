<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Mapper;

use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Secomm\EInvoiceMisa\Model\Config\MisaConfig;

/**
 * Adds shipping, cart discount and fee lines so MeInvoice totals align with Magento grand totals.
 */
class OrderInvoiceAdjustments
{
    private const ITEM_TYPE_GOODS = 1;
    private const SHIPPING_SKU = 'SHIPPING';
    private const ADJUSTMENT_FEE_SKU = 'ADJUSTMENT_FEE';
    private const ADJUSTMENT_REFUND_SKU = 'ADJUSTMENT_REFUND';
    private const KCT_RATE_NAME = 'KCT';

    public function __construct(
        private readonly SalesInvoiceContext $salesInvoiceContext,
        private readonly MisaConfig $misaConfig
    ) {
    }

    /**
     * Apply shipping, cart discount and rounding fee lines for a sales order invoice.
     *
     * @param OrderInterface $order
     * @param float $exchangeRate Transaction currency to VND rate.
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, array<string, mixed>> $taxRateInfo
     * @param array{
     *     amountWithoutVatOc: float,
     *     vatAmountOc: float,
     *     amountWithoutVat: float,
     *     vatAmount: float
     * } $totals
     * @param int $lineNumber Next MeInvoice line number (updated in place).
     * @return array{discountOc: float, discount: float, feeInfo: array<int, array<string, mixed>>}
     */
    public function applyForOrder(
        OrderInterface $order,
        float $exchangeRate,
        array &$lines,
        array &$taxRateInfo,
        array &$totals,
        int &$lineNumber
    ): array {
        $feeInfo = [];

        $this->appendShippingLine(
            (float) $order->getShippingAmount(),
            (float) $order->getShippingTaxAmount(),
            $exchangeRate,
            $this->misaConfig->getShippingLineName((int) $order->getStoreId()),
            $lines,
            $taxRateInfo,
            $totals,
            $lineNumber
        );

        $discountOc = abs((float) $order->getDiscountAmount());
        $discount = $this->salesInvoiceContext->convertOcToVnd($discountOc, $exchangeRate);
        $this->subtractDiscount($discountOc, $discount, $totals);

        $this->applyRoundingFee(
            (float) $order->getGrandTotal(),
            $totals,
            $feeInfo,
            $exchangeRate
        );

        return [
            'discountOc' => $discountOc,
            'discount' => $discount,
            'feeInfo' => $feeInfo,
        ];
    }

    /**
     * Apply shipping, discount and rounding adjustments for a credit memo adjustment invoice.
     *
     * @param CreditmemoInterface $creditmemo
     * @param float $exchangeRate Transaction currency to VND rate.
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, array<string, mixed>> $taxRateInfo
     * @param array{
     *     amountWithoutVatOc: float,
     *     vatAmountOc: float,
     *     amountWithoutVat: float,
     *     vatAmount: float
     * } $totals
     * @param int $lineNumber Next MeInvoice line number (updated in place).
     * @return array{discountOc: float, discount: float, feeInfo: array<int, array<string, mixed>>}
     */
    public function applyForCreditmemo(
        CreditmemoInterface $creditmemo,
        float $exchangeRate,
        array &$lines,
        array &$taxRateInfo,
        array &$totals,
        int &$lineNumber
    ): array {
        $feeInfo = [];

        $this->appendShippingLine(
            (float) $creditmemo->getShippingAmount(),
            (float) $creditmemo->getShippingTaxAmount(),
            $exchangeRate,
            $this->misaConfig->getShippingLineName((int) $creditmemo->getStoreId()),
            $lines,
            $taxRateInfo,
            $totals,
            $lineNumber
        );

        $discountOc = abs((float) $creditmemo->getDiscountAmount());
        $discount = $this->salesInvoiceContext->convertOcToVnd($discountOc, $exchangeRate);
        $this->subtractDiscount($discountOc, $discount, $totals);

        $this->appendCreditmemoAdjustmentLines(
            $creditmemo,
            $exchangeRate,
            $lines,
            $taxRateInfo,
            $totals,
            $lineNumber
        );

        $grandTotalOc = (float) $creditmemo->getGrandTotal();
        if ($grandTotalOc < 0) {
            $grandTotalOc = abs($grandTotalOc);
        }
        // Only patch sub-cent drift; do not hide unmapped CM components in FeeInfo.
        $this->applyRoundingFee($grandTotalOc, $totals, $feeInfo, $exchangeRate, false);

        return [
            'discountOc' => $discountOc,
            'discount' => $discount,
            'feeInfo' => $feeInfo,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, array<string, mixed>> $taxRateInfo
     * @param array{
     *     amountWithoutVatOc: float,
     *     vatAmountOc: float,
     *     amountWithoutVat: float,
     *     vatAmount: float
     * } $totals
     */
    private function appendShippingLine(
        float $shippingExclTaxOc,
        float $shippingTaxOc,
        float $exchangeRate,
        string $shippingLineName,
        array &$lines,
        array &$taxRateInfo,
        array &$totals,
        int &$lineNumber
    ): void {
        if ($shippingExclTaxOc <= 0.0 && $shippingTaxOc <= 0.0) {
            return;
        }

        $amountWithoutVatOc = $shippingExclTaxOc;
        $vatAmountOc = $shippingTaxOc;
        $amountWithoutVat = $this->salesInvoiceContext->convertOcToVnd($amountWithoutVatOc, $exchangeRate);
        $vatAmount = $this->salesInvoiceContext->convertOcToVnd($vatAmountOc, $exchangeRate);
        $taxPercent = $amountWithoutVatOc > 0.0
            ? round(($vatAmountOc / $amountWithoutVatOc) * 100, 2)
            : 0.0;
        $vatRateName = $taxPercent > 0 ? $this->formatVatRateName($taxPercent) : self::KCT_RATE_NAME;

        $totals['amountWithoutVatOc'] += $amountWithoutVatOc;
        $totals['vatAmountOc'] += $vatAmountOc;
        $totals['amountWithoutVat'] += $amountWithoutVat;
        $totals['vatAmount'] += $vatAmount;

        // KCT / non-tax shipping stays on InvoiceDetail only — omit from TaxRateInfo.
        if ($vatRateName !== self::KCT_RATE_NAME) {
            $this->accumulateTaxRate(
                $taxRateInfo,
                $vatRateName,
                $amountWithoutVatOc,
                $vatAmountOc,
                $amountWithoutVat,
                $vatAmount
            );
        }

        $lineNumber++;
        $lines[] = [
            'ItemType' => self::ITEM_TYPE_GOODS,
            'LineNumber' => $lineNumber,
            'SortOrder' => $lineNumber,
            'ItemCode' => self::SHIPPING_SKU,
            'ItemName' => $shippingLineName,
            'UnitName' => 'Lần',
            'Quantity' => 1.0,
            'UnitPrice' => $amountWithoutVatOc,
            'AmountOC' => $amountWithoutVatOc,
            'Amount' => $amountWithoutVat,
            'AmountWithoutVATOC' => $amountWithoutVatOc,
            'AmountWithoutVAT' => $amountWithoutVat,
            'VATRateName' => $vatRateName,
            'VATAmountOC' => $vatAmountOc,
            'VATAmount' => $vatAmount,
        ];
    }

    /**
     * @param array{
     *     amountWithoutVatOc: float,
     *     vatAmountOc: float,
     *     amountWithoutVat: float,
     *     vatAmount: float
     * } $totals
     */
    private function subtractDiscount(float $discountOc, float $discount, array &$totals): void
    {
        if ($discountOc <= 0.0) {
            return;
        }

        $totals['amountWithoutVatOc'] = max(0.0, $totals['amountWithoutVatOc'] - $discountOc);
        $totals['amountWithoutVat'] = max(0.0, $totals['amountWithoutVat'] - $discount);
    }

    /**
     * @param array{
     *     amountWithoutVatOc: float,
     *     vatAmountOc: float,
     *     amountWithoutVat: float,
     *     vatAmount: float
     * } $totals
     * @param array<int, array<string, mixed>> $feeInfo
     */
    /**
     * Map Magento credit memo adjustment fields as explicit invoice lines.
     *
     * adjustment_negative = Adjustment Fee (reduces refund/grand total).
     * adjustment_positive = Adjustment Refund (extra refund to customer).
     *
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, array<string, mixed>> $taxRateInfo
     * @param array{
     *     amountWithoutVatOc: float,
     *     vatAmountOc: float,
     *     amountWithoutVat: float,
     *     vatAmount: float
     * } $totals
     */
    private function appendCreditmemoAdjustmentLines(
        CreditmemoInterface $creditmemo,
        float $exchangeRate,
        array &$lines,
        array &$taxRateInfo,
        array &$totals,
        int &$lineNumber
    ): void {
        $adjustmentFeeOc = (float) $creditmemo->getAdjustmentNegative();
        if ($adjustmentFeeOc > 0.0) {
            $this->appendSignedLine(
                -$adjustmentFeeOc,
                self::ADJUSTMENT_FEE_SKU,
                (string) __('Adjustment Fee'),
                $exchangeRate,
                $lines,
                $taxRateInfo,
                $totals,
                $lineNumber
            );
        }

        $adjustmentRefundOc = (float) $creditmemo->getAdjustmentPositive();
        if ($adjustmentRefundOc > 0.0) {
            $this->appendSignedLine(
                $adjustmentRefundOc,
                self::ADJUSTMENT_REFUND_SKU,
                (string) __('Adjustment Refund'),
                $exchangeRate,
                $lines,
                $taxRateInfo,
                $totals,
                $lineNumber
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, array<string, mixed>> $taxRateInfo
     * @param array{
     *     amountWithoutVatOc: float,
     *     vatAmountOc: float,
     *     amountWithoutVat: float,
     *     vatAmount: float
     * } $totals
     */
    private function appendSignedLine(
        float $amountWithoutVatOc,
        string $itemCode,
        string $itemName,
        float $exchangeRate,
        array &$lines,
        array &$taxRateInfo,
        array &$totals,
        int &$lineNumber
    ): void {
        if (abs($amountWithoutVatOc) < 0.0001) {
            return;
        }

        $vatAmountOc = 0.0;
        $amountWithoutVat = $this->salesInvoiceContext->convertOcToVnd($amountWithoutVatOc, $exchangeRate);
        $vatAmount = 0.0;
        $vatRateName = self::KCT_RATE_NAME;

        $totals['amountWithoutVatOc'] += $amountWithoutVatOc;
        $totals['vatAmountOc'] += $vatAmountOc;
        $totals['amountWithoutVat'] += $amountWithoutVat;
        $totals['vatAmount'] += $vatAmount;

        $this->accumulateTaxRate(
            $taxRateInfo,
            $vatRateName,
            $amountWithoutVatOc,
            $vatAmountOc,
            $amountWithoutVat,
            $vatAmount
        );

        $lineNumber++;
        $lines[] = [
            'ItemType' => self::ITEM_TYPE_GOODS,
            'LineNumber' => $lineNumber,
            'SortOrder' => $lineNumber,
            'ItemCode' => $itemCode,
            'ItemName' => $itemName,
            'UnitName' => 'Lần',
            'Quantity' => 1.0,
            'UnitPrice' => $amountWithoutVatOc,
            'AmountOC' => $amountWithoutVatOc,
            'Amount' => $amountWithoutVat,
            'AmountWithoutVATOC' => $amountWithoutVatOc,
            'AmountWithoutVAT' => $amountWithoutVat,
            'VATRateName' => $vatRateName,
            'VATAmountOC' => $vatAmountOc,
            'VATAmount' => $vatAmount,
        ];
    }

    private function applyRoundingFee(
        float $grandTotalOc,
        array &$totals,
        array &$feeInfo,
        float $exchangeRate,
        bool $allowLargeDiff = true
    ): void {
        $computedTotalOc = $totals['amountWithoutVatOc'] + $totals['vatAmountOc'];
        $diffOc = round($grandTotalOc - $computedTotalOc, 2);
        if (abs($diffOc) < 0.01) {
            return;
        }

        if (!$allowLargeDiff) {
            return;
        }

        $feeInfo[] = [
            'FeeName' => 'Điều chỉnh',
            'FeeAmountOC' => $diffOc,
            'FeeAmount' => $this->salesInvoiceContext->convertOcToVnd($diffOc, $exchangeRate),
        ];
        if ($diffOc >= 0) {
            $totals['amountWithoutVatOc'] += $diffOc;
            $totals['amountWithoutVat'] += $this->salesInvoiceContext->convertOcToVnd($diffOc, $exchangeRate);
        } else {
            $totals['amountWithoutVatOc'] += $diffOc;
            $totals['amountWithoutVat'] += $this->salesInvoiceContext->convertOcToVnd($diffOc, $exchangeRate);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $taxRateInfo
     */
    private function accumulateTaxRate(
        array &$taxRateInfo,
        string $vatRateName,
        float $amountWithoutVatOc,
        float $vatAmountOc,
        float $amountWithoutVat,
        float $vatAmount
    ): void {
        if (!isset($taxRateInfo[$vatRateName])) {
            $taxRateInfo[$vatRateName] = [
                'VATRateName' => $vatRateName,
                'AmountWithoutVATOC' => 0.0,
                'VATAmountOC' => 0.0,
                'AmountWithoutVAT' => 0.0,
                'VATAmount' => 0.0,
            ];
        }
        $taxRateInfo[$vatRateName]['AmountWithoutVATOC'] += $amountWithoutVatOc;
        $taxRateInfo[$vatRateName]['VATAmountOC'] += $vatAmountOc;
        $taxRateInfo[$vatRateName]['AmountWithoutVAT'] += $amountWithoutVat;
        $taxRateInfo[$vatRateName]['VATAmount'] += $vatAmount;
    }

    private function formatVatRateName(float $taxPercent): string
    {
        if ((float) (int) $taxPercent === $taxPercent) {
            return (string) ((int) $taxPercent) . '%';
        }

        return rtrim(rtrim(sprintf('%.4F', $taxPercent), '0'), '.') . '%';
    }
}
