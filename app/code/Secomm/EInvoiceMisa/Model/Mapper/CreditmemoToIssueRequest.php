<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Mapper;

use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\CreditmemoItemInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Creditmemo as CreditmemoModel;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItemModel;
use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;
use Secomm\EInvoiceCore\Model\Data\IssueRequestFactory;
use Secomm\EInvoiceMisa\Model\Config\InvoiceAmountFormatConfig;
use Secomm\EInvoiceMisa\Model\Config\MisaConfig;
use Secomm\EInvoiceMisa\Model\Template\InvoiceTemplate;

/**
 * Maps a Magento credit memo to a MeInvoice adjustment (hoa don dieu chinh) payload.
 */
class CreditmemoToIssueRequest
{
    private const REFERENCE_TYPE_ADJUSTMENT = 2;

    /**
     * @param IssueRequestFactory $issueRequestFactory
     * @param MisaConfig $misaConfig
     * @param SalesInvoiceContext $salesInvoiceContext
     * @param OrderInvoiceAdjustments $orderInvoiceAdjustments
     */
    public function __construct(
        private readonly IssueRequestFactory $issueRequestFactory,
        private readonly MisaConfig $misaConfig,
        private readonly SalesInvoiceContext $salesInvoiceContext,
        private readonly OrderInvoiceAdjustments $orderInvoiceAdjustments,
        private readonly InvoiceAmountFormatConfig $amountFormatConfig
    ) {
    }

    /**
     * Build an adjustment issue request referencing the original invoice.
     *
     * @param CreditmemoInterface $creditmemo
     * @param InvoiceTemplate $template
     * @param array<string, string> $origin Original invoice meta (transaction_id, ref_id, inv_series, inv_date).
     * @return IssueRequestInterface
     */
    public function map(CreditmemoInterface $creditmemo, InvoiceTemplate $template, array $origin): IssueRequestInterface
    {
        $order = $creditmemo->getOrder();
        $billing = $order?->getBillingAddress();
        $invDate = substr((string) ($creditmemo->getCreatedAt() ?: date('Y-m-d')), 0, 10);
        $exchangeRate = $this->salesInvoiceContext->getExchangeRateFromCreditmemo($creditmemo, $order);

        $totals = [
            'amountWithoutVatOc' => 0.0,
            'vatAmountOc' => 0.0,
            'amountWithoutVat' => 0.0,
            'vatAmount' => 0.0,
        ];
        $taxRateInfo = [];
        $lines = [];

        $index = 0;
        foreach ($this->resolveCreditmemoItems($creditmemo) as $item) {
            if ($this->shouldSkipCreditmemoItem($item)) {
                continue;
            }
            $qty = (float) $item->getQty();
            $amountWithoutVatOc = (float) $item->getRowTotal();
            $vatAmountOc = (float) $item->getTaxAmount();
            $amountWithoutVat = $this->salesInvoiceContext->convertOcToVnd($amountWithoutVatOc, $exchangeRate);
            $vatAmount = $this->salesInvoiceContext->convertOcToVnd($vatAmountOc, $exchangeRate);
            // MeInvoice: AmountOC = UnitPrice × Quantity — UnitPrice is in transaction currency (OC).
            $unitPriceOc = $qty > 0 ? ($amountWithoutVatOc / $qty) : 0.0;
            $taxPercent = $amountWithoutVatOc > 0.0 ? round(($vatAmountOc / $amountWithoutVatOc) * 100, 2) : 0.0;
            $vatRateName = $taxPercent > 0 ? $this->formatVatRateName($taxPercent) : 'KCT';

            $totals['amountWithoutVatOc'] += $amountWithoutVatOc;
            $totals['vatAmountOc'] += $vatAmountOc;
            $totals['amountWithoutVat'] += $amountWithoutVat;
            $totals['vatAmount'] += $vatAmount;

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

            $lines[] = [
                'ItemType' => 1,
                'LineNumber' => $index + 1,
                'SortOrder' => $index + 1,
                'ItemCode' => (string) $item->getSku(),
                'ItemName' => (string) $item->getName(),
                'UnitName' => 'Cái',
                'Quantity' => $qty,
                'UnitPrice' => $unitPriceOc,
                'AmountOC' => $amountWithoutVatOc,
                'Amount' => $amountWithoutVat,
                'AmountWithoutVATOC' => $amountWithoutVatOc,
                'AmountWithoutVAT' => $amountWithoutVat,
                'VATRateName' => $vatRateName,
                'VATAmountOC' => $vatAmountOc,
                'VATAmount' => $vatAmount,
            ];
            $index++;
        }

        if ($index === 0) {
            $index = $this->appendSubtotalFallbackLine(
                $creditmemo,
                $exchangeRate,
                $lines,
                $taxRateInfo,
                $totals,
                $index
            );
        }

        $lineNumber = count($lines);
        $adjustmentMeta = $this->orderInvoiceAdjustments->applyForCreditmemo(
            $creditmemo,
            $exchangeRate,
            $lines,
            $taxRateInfo,
            $totals,
            $lineNumber
        );

        $totalAmountWithoutVatOc = $totals['amountWithoutVatOc'];
        $totalVatAmountOc = $totals['vatAmountOc'];
        $totalAmountWithoutVat = $totals['amountWithoutVat'];
        $totalVatAmount = $totals['vatAmount'];
        $totalAmountOc = $totalAmountWithoutVatOc + $totalVatAmountOc;
        $totalAmount = $totalAmountWithoutVat + $totalVatAmount;

        $buyerEmail = (string) ($billing?->getEmail() ?: $order?->getCustomerEmail());
        $storeId = (int) $creditmemo->getStoreId();
        $isSendEmail = $this->misaConfig->isSendEmailOnIssue($storeId) && $buyerEmail !== '';

        $payload = [
            'RefID' => $this->buildRefId($creditmemo),
            'InvSeries' => $template->getInvSeries(),
            'InvoiceTemplateID' => $template->getIpTemplateId(),
            'InvoiceName' => 'Hóa đơn điều chỉnh',
            'InvDate' => $invDate,
            'CurrencyCode' => $this->salesInvoiceContext->getCurrencyCodeFromCreditmemo($creditmemo, $order),
            'ExchangeRate' => $exchangeRate,
            'PaymentMethodName' => $this->salesInvoiceContext->getPaymentMethodNameFromCreditmemo($order),
            'ReferenceType' => self::REFERENCE_TYPE_ADJUSTMENT,
            'OrgInvoiceTransactionID' => '',
            'OrgRefID' => '',
            'OrgInvSeries' => '',
            'OrgInvDate' => '',
            'OrgInvNo' => '',
            'OrgInvTemplateNo' => '',
            'InvoiceNote' => (string) ($creditmemo->getCustomerNote() ?: __('Credit memo adjustment')->render()),
            'BuyerFullName' => trim((string) ($billing?->getFirstname() . ' ' . $billing?->getLastname())) ?: 'Khách lẻ',
            'BuyerLegalName' => (string) ($billing?->getCompany() ?: ''),
            'BuyerTaxCode' => (string) ($billing?->getVatId() ?: ''),
            'BuyerPhoneNumber' => (string) ($billing?->getTelephone() ?: ''),
            'BuyerAddress' => $billing ? implode(', ', array_filter([
                implode(' ', $billing->getStreet() ?? []),
                $billing->getCity(),
                $billing->getRegion(),
            ])) : '',
            'BuyerEmail' => $buyerEmail,
            'IsSendEmail' => $isSendEmail,
            'ReceiverEmail' => $buyerEmail,
            'TotalSaleAmountOC' => $totalAmountWithoutVatOc,
            'TotalSaleAmount' => $totalAmountWithoutVat,
            'TotalAmountWithoutVATOC' => $totalAmountWithoutVatOc,
            'TotalAmountWithoutVAT' => $totalAmountWithoutVat,
            'DiscountRate' => 0,
            'TotalDiscountAmountOC' => $adjustmentMeta['discountOc'],
            'TotalDiscountAmount' => $adjustmentMeta['discount'],
            'TotalVATAmountOC' => $totalVatAmountOc,
            'TotalVATAmount' => $totalVatAmount,
            'TotalAmountOC' => $totalAmountOc,
            'TotalAmount' => $totalAmount,
            'TaxRateInfo' => array_values($taxRateInfo),
            'InvoiceDetail' => $lines,
            'OriginalInvoiceDetail' => $lines,
            'FeeInfo' => $adjustmentMeta['feeInfo'],
            'OptionUserDefined' => $this->amountFormatConfig->getOptionUserDefined((int) $creditmemo->getStoreId()),
            'ClockInfos' => [],
        ];
        OrgInvoiceReferenceNormalizer::applyOrgFields($payload, $origin);

        return $this->issueRequestFactory->create()
            ->setOrderId((int) ($order?->getEntityId() ?: 0))
            ->setOrderIncrementId((string) ($order?->getIncrementId() ?: ''))
            ->setStoreId((int) $creditmemo->getStoreId())
            ->setPayload($payload);
    }

    /**
     * Deterministic GUID-like RefID for the adjustment invoice.
     *
     * @param CreditmemoInterface $creditmemo
     * @return string
     */
    private function buildRefId(CreditmemoInterface $creditmemo): string
    {
        $seed = sprintf(
            'secomm-einvoice-adj:%s:%s',
            (string) $creditmemo->getStoreId(),
            (string) ($creditmemo->getIncrementId() ?: $creditmemo->getEntityId())
        );
        $hex = substr(hash('sha256', $seed), 0, 32);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * @param float $taxPercent
     * @return string
     */
    private function formatVatRateName(float $taxPercent): string
    {
        if ((float) (int) $taxPercent === $taxPercent) {
            return (string) ((int) $taxPercent) . '%';
        }

        return rtrim(rtrim(sprintf('%.4F', $taxPercent), '0'), '.') . '%';
    }

    /**
     * @return CreditmemoItemInterface[]
     */
    private function resolveCreditmemoItems(CreditmemoInterface $creditmemo): array
    {
        if ($creditmemo instanceof CreditmemoModel) {
            return $creditmemo->getAllItems();
        }

        return $creditmemo->getItems() ?? [];
    }

    /**
     * When CM subtotal is set but no item rows were mapped, add one summary goods line.
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
    private function appendSubtotalFallbackLine(
        CreditmemoInterface $creditmemo,
        float $exchangeRate,
        array &$lines,
        array &$taxRateInfo,
        array &$totals,
        int $index
    ): int {
        $subtotalOc = (float) $creditmemo->getSubtotal();
        if ($subtotalOc <= 0.0) {
            return $index;
        }

        $taxOc = (float) $creditmemo->getTaxAmount();
        $goodsTaxOc = max(0.0, $taxOc - (float) $creditmemo->getShippingTaxAmount());
        $amountWithoutVatOc = $subtotalOc;
        $vatAmountOc = $goodsTaxOc;
        $amountWithoutVat = $this->salesInvoiceContext->convertOcToVnd($amountWithoutVatOc, $exchangeRate);
        $vatAmount = $this->salesInvoiceContext->convertOcToVnd($vatAmountOc, $exchangeRate);
        $taxPercent = $amountWithoutVatOc > 0.0 ? round(($vatAmountOc / $amountWithoutVatOc) * 100, 2) : 0.0;
        $vatRateName = $taxPercent > 0 ? $this->formatVatRateName($taxPercent) : 'KCT';

        $totals['amountWithoutVatOc'] += $amountWithoutVatOc;
        $totals['vatAmountOc'] += $vatAmountOc;
        $totals['amountWithoutVat'] += $amountWithoutVat;
        $totals['vatAmount'] += $vatAmount;

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

        $lines[] = [
            'ItemType' => 1,
            'LineNumber' => $index + 1,
            'SortOrder' => $index + 1,
            'ItemCode' => 'CREDITMEMO_SUBTOTAL',
            'ItemName' => (string) __('Refunded goods'),
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

        return $index + 1;
    }

    private function shouldSkipCreditmemoItem(CreditmemoItemInterface $item): bool
    {
        if ($item instanceof CreditmemoItemModel) {
            if ($item->isDeleted()) {
                return true;
            }

            $orderItem = $item->getOrderItem();
            if ($orderItem !== null) {
                if ($orderItem->isDummy()) {
                    return true;
                }
                if ($orderItem->getHasChildren()) {
                    return true;
                }
            }
        }

        $qty = (float) $item->getQty();
        $rowTotal = (float) $item->getRowTotal();

        return $qty <= 0.0 && abs($rowTotal) < 0.0001;
    }
}
