<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Launchpad\MageplazaExtraFeeFix\Service\EInvoice;

use Launchpad\MageplazaExtraFeeFix\Service\ExtraFeeOrderHelper;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;
use Secomm\EInvoiceMisa\Model\Mapper\SalesInvoiceContext;

/**
 * Adds Mageplaza extra-fee lines (e.g. "Phí bảo hiểm hàng hóa") to a MeInvoice payload.
 *
 * The Misa mappers fold unmapped order components into a generic "Điều chỉnh"
 * FeeInfo entry; this processor promotes the extra fees to named InvoiceDetail
 * lines instead, keeping the Secomm_EInvoice* modules untouched.
 */
class ExtraFeeInvoicePayloadProcessor
{
    private const ITEM_TYPE_GOODS = 1;
    private const KCT_RATE_NAME = 'KCT';
    private const FEE_LINE_CODE_PATTERN = '/^EXTRA_FEE_RULE_\d+$/';
    private const TOLERANCE = 0.01;

    public function __construct(
        private readonly ExtraFeeOrderHelper $extraFeeOrderHelper,
        private readonly SalesInvoiceContext $salesInvoiceContext
    ) {
    }

    /**
     * Order flow: the mapper already folded the fee into the header totals via the
     * "Điều chỉnh" FeeInfo diff, so lines are added and the diff is absorbed.
     */
    public function processForOrder(IssueRequestInterface $request, Order $order): IssueRequestInterface
    {
        return $this->apply($request, $this->extraFeeOrderHelper->getFeeTotals($order), true);
    }

    /**
     * Credit memo flow: headers are line sums (no rounding diff), so header totals
     * are bumped along with the new lines.
     */
    public function processForCreditmemo(IssueRequestInterface $request, Creditmemo $creditmemo): IssueRequestInterface
    {
        return $this->apply($request, $this->extraFeeOrderHelper->getFeeTotals($creditmemo), false);
    }

    /**
     * @param IssueRequestInterface $request
     * @param array<int, array<string, mixed>> $fees
     * @param bool $absorbFromFeeInfo Order flow: subtract the fee from the rounding FeeInfo entry.
     * @return IssueRequestInterface
     */
    private function apply(IssueRequestInterface $request, array $fees, bool $absorbFromFeeInfo): IssueRequestInterface
    {
        if ($fees === []) {
            return $request;
        }

        $payload = $request->getPayload();
        if (!is_array($payload['InvoiceDetail'] ?? null) || $payload['InvoiceDetail'] === []) {
            return $request;
        }

        // mapReplacement() re-enters map() through the interceptor — never apply twice.
        if ($this->alreadyApplied($payload['InvoiceDetail'])) {
            return $request;
        }

        $exchangeRate = (float) ($payload['ExchangeRate'] ?? 1.0);
        $addedExclOc = 0.0;

        foreach ($fees as $fee) {
            $amountOc = (float) $fee['amount_excl_tax'];
            $taxOc = (float) $fee['tax_amount'];
            $amount = $this->salesInvoiceContext->convertOcToVnd($amountOc, $exchangeRate);
            $tax = $this->salesInvoiceContext->convertOcToVnd($taxOc, $exchangeRate);
            $taxPercent = $amountOc > 0.0 ? round(($taxOc / $amountOc) * 100, 2) : 0.0;
            $vatRateName = $taxPercent > 0.0 ? $this->formatVatRateName($taxPercent) : self::KCT_RATE_NAME;

            $line = [
                'ItemType' => self::ITEM_TYPE_GOODS,
                'ItemCode' => $this->normalizeItemCode((string) $fee['code']),
                'ItemName' => (string) $fee['title'],
                'UnitName' => 'Lần',
                'Quantity' => 1.0,
                'UnitPrice' => $amountOc,
                'AmountOC' => $amountOc,
                'Amount' => $amount,
                'AmountWithoutVATOC' => $amountOc,
                'AmountWithoutVAT' => $amount,
                'VATRateName' => $vatRateName,
                'VATAmountOC' => $taxOc,
                'VATAmount' => $tax,
            ];

            foreach (['InvoiceDetail', 'OriginalInvoiceDetail'] as $key) {
                if (isset($payload[$key]) && is_array($payload[$key])) {
                    $lineNumber = $this->nextLineNumber($payload[$key]);
                    $line['LineNumber'] = $lineNumber;
                    $line['SortOrder'] = $lineNumber;
                    $payload[$key][] = $line;
                }
            }

            $this->accumulateTaxRate($payload, $vatRateName, $amountOc, $taxOc, $amount, $tax);

            // ponytail: taxed fee rules are untested (all live rules are KCT) — the tax is
            // moved to the VAT headers but stays double-counted inside the order-flow diff;
            // extend absorbRoundingFeeInfo if a taxed rule ever ships.
            if ($taxOc > 0.0) {
                $payload['TotalVATAmountOC'] = (float) ($payload['TotalVATAmountOC'] ?? 0) + $taxOc;
                $payload['TotalVATAmount'] = (float) ($payload['TotalVATAmount'] ?? 0) + $tax;
                $payload['TotalAmountOC'] = (float) ($payload['TotalAmountOC'] ?? 0) + $taxOc;
                $payload['TotalAmount'] = (float) ($payload['TotalAmount'] ?? 0) + $tax;
            }

            if (!$absorbFromFeeInfo) {
                $payload['TotalSaleAmountOC'] = (float) ($payload['TotalSaleAmountOC'] ?? 0) + $amountOc;
                $payload['TotalSaleAmount'] = (float) ($payload['TotalSaleAmount'] ?? 0) + $amount;
                $payload['TotalAmountWithoutVATOC'] = (float) ($payload['TotalAmountWithoutVATOC'] ?? 0) + $amountOc;
                $payload['TotalAmountWithoutVAT'] = (float) ($payload['TotalAmountWithoutVAT'] ?? 0) + $amount;
                $payload['TotalAmountOC'] = (float) ($payload['TotalAmountOC'] ?? 0) + $amountOc;
                $payload['TotalAmount'] = (float) ($payload['TotalAmount'] ?? 0) + $amount;
            }

            $addedExclOc += $amountOc;
        }

        if ($absorbFromFeeInfo) {
            $this->absorbRoundingFeeInfo($payload, $addedExclOc, $exchangeRate);
        }

        $request->setPayload($payload);

        return $request;
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return bool
     */
    private function alreadyApplied(array $lines): bool
    {
        foreach ($lines as $line) {
            if (is_array($line) && preg_match(self::FEE_LINE_CODE_PATTERN, (string) ($line['ItemCode'] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return int
     */
    private function nextLineNumber(array $lines): int
    {
        $max = 0;
        foreach ($lines as $line) {
            $max = max($max, (int) ($line['LineNumber'] ?? 0), (int) ($line['SortOrder'] ?? 0));
        }

        return $max + 1;
    }

    /**
     * Subtract the promoted fee amount from the "Điều chỉnh" rounding entries so
     * the payload keeps balancing (header totals already include the diff).
     *
     * @param array<string, mixed> $payload
     */
    private function absorbRoundingFeeInfo(array &$payload, float $addedExclOc, float $exchangeRate): void
    {
        $remaining = round($addedExclOc, 2);
        if (abs($remaining) < self::TOLERANCE) {
            return;
        }

        $feeInfo = is_array($payload['FeeInfo'] ?? null) ? $payload['FeeInfo'] : [];
        foreach ($feeInfo as $index => $fee) {
            if ($remaining <= 0.0 || !is_array($fee)) {
                continue;
            }

            $currentOc = (float) ($fee['FeeAmountOC'] ?? 0);
            if ($currentOc <= 0.0) {
                continue;
            }

            $take = min($currentOc, $remaining);
            $newOc = round($currentOc - $take, 2);
            if (abs($newOc) < self::TOLERANCE) {
                unset($feeInfo[$index]);
            } else {
                $fee['FeeAmountOC'] = $newOc;
                $fee['FeeAmount'] = round(
                    (float) ($fee['FeeAmount'] ?? 0) - $this->salesInvoiceContext->convertOcToVnd($take, $exchangeRate),
                    2
                );
                $feeInfo[$index] = $fee;
            }
            $remaining = round($remaining - $take, 2);
        }

        $payload['FeeInfo'] = array_values($feeInfo);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function accumulateTaxRate(
        array &$payload,
        string $vatRateName,
        float $amountOc,
        float $taxOc,
        float $amount,
        float $tax
    ): void {
        $rates = is_array($payload['TaxRateInfo'] ?? null) ? $payload['TaxRateInfo'] : [];

        foreach ($rates as &$row) {
            if (($row['VATRateName'] ?? '') === $vatRateName) {
                $row['AmountWithoutVATOC'] = (float) ($row['AmountWithoutVATOC'] ?? 0) + $amountOc;
                $row['VATAmountOC'] = (float) ($row['VATAmountOC'] ?? 0) + $taxOc;
                $row['AmountWithoutVAT'] = (float) ($row['AmountWithoutVAT'] ?? 0) + $amount;
                $row['VATAmount'] = (float) ($row['VATAmount'] ?? 0) + $tax;
                unset($row);
                $payload['TaxRateInfo'] = $rates;

                return;
            }
        }
        unset($row);

        $rates[] = [
            'VATRateName' => $vatRateName,
            'AmountWithoutVATOC' => $amountOc,
            'VATAmountOC' => $taxOc,
            'AmountWithoutVAT' => $amount,
            'VATAmount' => $tax,
        ];
        $payload['TaxRateInfo'] = $rates;
    }

    /**
     * "mp_extra_fee_rule_1_auto" → "EXTRA_FEE_RULE_1".
     */
    private function normalizeItemCode(string $code): string
    {
        if (preg_match('/mp_extra_fee_rule_(\d+)/', $code, $matches)) {
            return 'EXTRA_FEE_RULE_' . $matches[1];
        }

        $normalized = strtoupper(preg_replace('/[^a-z0-9]+/i', '_', $code) ?? '');

        return $normalized !== '' ? $normalized : 'EXTRA_FEE';
    }

    private function formatVatRateName(float $taxPercent): string
    {
        if ((float) (int) $taxPercent === $taxPercent) {
            return (string) ((int) $taxPercent) . '%';
        }

        return rtrim(rtrim(sprintf('%.4F', $taxPercent), '0'), '.') . '%';
    }
}
