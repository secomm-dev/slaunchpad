<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Mapper;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;
use Secomm\EInvoiceCore\Model\Data\IssueRequestFactory;
use Secomm\EInvoiceMisa\Model\Config\InvoiceAmountFormatConfig;
use Secomm\EInvoiceMisa\Model\Config\MisaConfig;
use Secomm\EInvoiceMisa\Model\Template\InvoiceTemplate;

/**
 * Maps Magento order to MeInvoice Integration API InvoiceData payload.
 */
class OrderToIssueRequest
{
    /**
     * @param IssueRequestFactory $issueRequestFactory
     * @param MisaConfig $misaConfig
     * @param SalesInvoiceContext $salesInvoiceContext
     * @param OrderInvoiceAdjustments $orderInvoiceAdjustments
     */
    private const REFERENCE_TYPE_REPLACE = 1;

    public function __construct(
        private readonly IssueRequestFactory $issueRequestFactory,
        private readonly MisaConfig $misaConfig,
        private readonly SalesInvoiceContext $salesInvoiceContext,
        private readonly OrderInvoiceAdjustments $orderInvoiceAdjustments,
        private readonly StoreManagerInterface $storeManager,
        private readonly InvoiceAmountFormatConfig $amountFormatConfig
    ) {
    }

    /**
     * Build canonical issue request with MeInvoice InvoiceData payload.
     *
     * @param OrderInterface $order
     * @param InvoiceTemplate $template
     * @param int $refRevision Increments the RefID after cancel/re-issue.
     * @return IssueRequestInterface
     */
    public function map(OrderInterface $order, InvoiceTemplate $template, int $refRevision = 0): IssueRequestInterface
    {
        $billing = $order->getBillingAddress();
        $invDate = (string) ($order->getCreatedAt() ?: date('Y-m-d'));
        $invDate = substr($invDate, 0, 10);
        $exchangeRate = $this->salesInvoiceContext->getExchangeRate($order);

        $totals = [
            'amountWithoutVatOc' => 0.0,
            'vatAmountOc' => 0.0,
            'amountWithoutVat' => 0.0,
            'vatAmount' => 0.0,
        ];
        $taxRateInfo = [];
        $lines = [];

        foreach ($order->getAllVisibleItems() as $index => $item) {
            /** @var OrderItemInterface $item */
            $qty = (float) $item->getQtyOrdered();
            $amountWithoutVatOc = (float) $item->getRowTotal();
            $vatAmountOc = (float) $item->getTaxAmount();
            $amountWithoutVat = $this->salesInvoiceContext->convertOcToVnd($amountWithoutVatOc, $exchangeRate);
            $vatAmount = $this->salesInvoiceContext->convertOcToVnd($vatAmountOc, $exchangeRate);
            // MeInvoice: AmountOC = UnitPrice × Quantity — UnitPrice is in transaction currency (OC).
            $unitPriceOc = $qty > 0 ? ($amountWithoutVatOc / $qty) : 0.0;
            $taxPercent = (float) $item->getTaxPercent();
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
                'LineNumber' => (int) $index + 1,
                'SortOrder' => (int) $index + 1,
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
        }

        $lineNumber = count($lines);
        $adjustmentMeta = $this->orderInvoiceAdjustments->applyForOrder(
            $order,
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

        $buyerEmail = (string) ($billing?->getEmail() ?: $order->getCustomerEmail());
        $buyerCompany = (string) ($billing?->getCompany() ?: '');
        $buyerTaxCode = (string) ($billing?->getVatId() ?: '');
        $storeId = (int) $order->getStoreId();
        $isSendEmail = $this->misaConfig->isSendEmailOnIssue($storeId) && $buyerEmail !== '';

        $payload = [
            'RefID' => $this->buildRefId($order, $refRevision),
            'InvSeries' => $template->getInvSeries(),
            'InvoiceTemplateID' => $template->getIpTemplateId(),
            'InvoiceName' => 'Hóa đơn giá trị gia tăng',
            'InvDate' => $invDate,
            'CurrencyCode' => $this->salesInvoiceContext->getCurrencyCode($order),
            'ExchangeRate' => $exchangeRate,
            'PaymentMethodName' => $this->salesInvoiceContext->getPaymentMethodName($order),
            'BuyerFullName' => trim((string) ($billing?->getFirstname() . ' ' . $billing?->getLastname())) ?: 'Khách lẻ',
            'BuyerLegalName' => $buyerCompany,
            'BuyerTaxCode' => $buyerTaxCode,
            'BuyerCode' => (string) ($order->getCustomerId() ?: ''),
            'BuyerOrderCode' => (string) $order->getIncrementId(),
            'SellerShopCode' => (string) $order->getStoreId(),
            'SellerShopName' => $this->resolveStoreName($storeId),
            'IsInvoiceSummary' => $template->isSendSummary(),
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
            'OptionUserDefined' => $this->amountFormatConfig->getOptionUserDefined($storeId),
            'ClockInfos' => [],
        ];

        return $this->issueRequestFactory->create()
            ->setOrderId((int) $order->getEntityId())
            ->setOrderIncrementId((string) $order->getIncrementId())
            ->setStoreId((int) $order->getStoreId())
            ->setPayload($payload);
    }

    /**
     * Build replacement invoice (ReferenceType=1) referencing the original.
     *
     * @param array<string, string> $origin
     */
    public function mapReplacement(
        OrderInterface $order,
        InvoiceTemplate $template,
        array $origin,
        int $refRevision = 0
    ): IssueRequestInterface {
        $request = $this->map($order, $template, $refRevision);
        $payload = $request->getPayload();
        $payload['ReferenceType'] = self::REFERENCE_TYPE_REPLACE;
        $payload['InvoiceName'] = 'Hóa đơn thay thế';
        $payload['InvoiceNote'] = (string) ($origin['invoice_note'] ?? 'Thay thế hóa đơn');
        OrgInvoiceReferenceNormalizer::applyOrgFields($payload, $origin);
        $request->setPayload($payload);

        return $request;
    }

    /**
     * Build deterministic GUID-like RefID for idempotent invoice issuing.
     *
     * The revision keeps the RefID stable across retries of the same attempt
     * but yields a fresh value once a prior invoice has been cancelled/re-issued.
     *
     * @param OrderInterface $order
     * @param int $refRevision
     * @return string
     */
    private function buildRefId(OrderInterface $order, int $refRevision = 0): string
    {
        $seed = sprintf(
            'secomm-einvoice:%s:%s:%d',
            (string) $order->getStoreId(),
            (string) $order->getIncrementId(),
            $refRevision
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
     * Format Magento tax percent as MeInvoice VATRateName (e.g. 10% or 8.5%).
     *
     * @param float $taxPercent
     * @return string
     */
    private function formatVatRateName(float $taxPercent): string
    {
        if ((float) (int) $taxPercent === $taxPercent) {
            return (string) ((int) $taxPercent) . '%';
        }

        $normalized = rtrim(rtrim(sprintf('%.4F', $taxPercent), '0'), '.');

        return $normalized . '%';
    }

    /**
     * Build decimal display options expected by MeInvoice InvoiceData.
     *
     * @return array<string, string>
     */
    private function resolveStoreName(int $storeId): string
    {
        try {
            return (string) $this->storeManager->getStore($storeId)->getName();
        } catch (\Exception) {
            return '';
        }
    }

}
