<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Mapper;

use Magento\Sales\Api\Data\OrderInterface;
use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;
use Secomm\EInvoiceCore\Model\Data\IssueRequestFactory;
use Secomm\EInvoiceMisa\Model\Config\InvoiceAmountFormatConfig;
use Secomm\EInvoiceMisa\Model\Config\MisaConfig;
use Secomm\EInvoiceMisa\Model\Template\InvoiceTemplate;

/**
 * Maps commercial discount (chiết khấu thương mại, ReferenceType=5) payload.
 */
class CommercialDiscountToIssueRequest
{
    private const REFERENCE_TYPE_COMMERCIAL_DISCOUNT = 5;

    public function __construct(
        private readonly IssueRequestFactory $issueRequestFactory,
        private readonly MisaConfig $misaConfig,
        private readonly SalesInvoiceContext $salesInvoiceContext,
        private readonly InvoiceAmountFormatConfig $amountFormatConfig
    ) {
    }

    /**
     * @param array<string, string> $origin Original invoice org fields
     * @param array<string, string|float> $discount list_no, list_date, invoice_note, amount, vat_amount
     */
    public function map(
        OrderInterface $order,
        InvoiceTemplate $template,
        array $origin,
        array $discount,
        int $refRevision = 0
    ): IssueRequestInterface {
        $billing = $order->getBillingAddress();
        $invDate = substr((string) ($order->getCreatedAt() ?: date('Y-m-d')), 0, 10);
        $exchangeRate = $this->salesInvoiceContext->getExchangeRate($order);

        $amountWithoutVatOc = -abs((float) ($discount['amount'] ?? 0));
        $vatAmountOc = -abs((float) ($discount['vat_amount'] ?? 0));
        $amountWithoutVat = $this->salesInvoiceContext->convertOcToVnd($amountWithoutVatOc, $exchangeRate);
        $vatAmount = $this->salesInvoiceContext->convertOcToVnd($vatAmountOc, $exchangeRate);
        $totalAmountOc = $amountWithoutVatOc + $vatAmountOc;
        $totalAmount = $amountWithoutVat + $vatAmount;
        $taxPercent = $amountWithoutVatOc !== 0.0
            ? round((abs($vatAmountOc) / abs($amountWithoutVatOc)) * 100, 2)
            : 0.0;
        $vatRateName = $taxPercent > 0 ? $this->formatVatRateName($taxPercent) : 'KCT';

        $line = [
            'LineNumber' => 1,
            'ItemType' => 1,
            'SortOrder' => 1,
            'ItemCode' => 'CKTM',
            'ItemName' => (string) ($discount['invoice_note'] ?: 'Chiết khấu thương mại'),
            'UnitName' => '',
            'Quantity' => 1.0,
            'UnitPrice' => $amountWithoutVatOc,
            'AmountOC' => $amountWithoutVatOc,
            'Amount' => $amountWithoutVat,
            'DiscountRate' => 0.0,
            'DiscountAmountOC' => 0.0,
            'DiscountAmount' => 0.0,
            'VATRateName' => $vatRateName,
            'VATAmountOC' => $vatAmountOc,
            'VATAmount' => $vatAmount,
        ];

        $taxRateInfo = $amountWithoutVatOc !== 0.0 ? [[
            'VATRateName' => $vatRateName,
            'AmountWithoutVATOC' => $amountWithoutVatOc,
            'VATAmountOC' => $vatAmountOc,
            'AmountWithoutVAT' => $amountWithoutVat,
            'VATAmount' => $vatAmount,
        ]] : [];

        $listDate = (string) ($discount['list_date'] ?? $invDate);
        if (strlen($listDate) > 10) {
            $listDate = substr($listDate, 0, 10);
        }

        $buyerEmail = (string) ($billing?->getEmail() ?: $order->getCustomerEmail());
        $isSendEmail = $this->misaConfig->isSendEmailOnIssue((int) $order->getStoreId()) && $buyerEmail !== '';

        $payload = [
            'RefID' => $this->buildRefId($order, $refRevision),
            'InvSeries' => $template->getInvSeries(),
            'InvoiceTemplateID' => $template->getIpTemplateId(),
            'InvoiceName' => 'Hóa đơn chiết khấu thương mại',
            'InvDate' => $invDate,
            'CurrencyCode' => $this->salesInvoiceContext->getCurrencyCode($order),
            'ExchangeRate' => $exchangeRate,
            'PaymentMethodName' => $this->salesInvoiceContext->getPaymentMethodName($order),
            'BuyerLegalName' => (string) ($billing?->getCompany() ?: ''),
            'BuyerTaxCode' => (string) ($billing?->getVatId() ?: ''),
            'BuyerAddress' => $billing ? implode(', ', array_filter([
                implode(' ', $billing->getStreet() ?? []),
                $billing->getCity(),
                $billing->getRegion(),
            ])) : '',
            'BuyerCode' => (string) ($order->getCustomerId() ?: ''),
            'BuyerFullName' => trim(
                (string) $billing?->getFirstname() . ' ' . (string) $billing?->getLastname()
            ) ?: 'Khách lẻ',
            'BuyerPhoneNumber' => (string) ($billing?->getTelephone() ?: ''),
            'BuyerEmail' => $buyerEmail,
            'IsSendEmail' => $isSendEmail,
            'ReferenceType' => self::REFERENCE_TYPE_COMMERCIAL_DISCOUNT,
            'ListNo' => (string) ($discount['list_no'] ?? ''),
            'ListDate' => $listDate,
            'InvoiceNote' => (string) ($discount['invoice_note'] ?? ''),
            'OrgInvoiceTransactionID' => '',
            'OrgRefID' => '',
            'OrgInvSeries' => '',
            'OrgInvDate' => '',
            'OrgInvNo' => '',
            'OrgInvTemplateNo' => '',
            'TotalSaleAmountOC' => $amountWithoutVatOc,
            'TotalSaleAmount' => $amountWithoutVat,
            'TotalAmountWithoutVATOC' => $amountWithoutVatOc,
            'TotalAmountWithoutVAT' => $amountWithoutVat,
            'TotalVATAmountOC' => $vatAmountOc,
            'TotalVATAmount' => $vatAmount,
            'TotalAmountOC' => $totalAmountOc,
            'TotalAmount' => $totalAmount,
            'TaxRateInfo' => $taxRateInfo,
            'InvoiceDetail' => [$line],
            'OriginalInvoiceDetail' => [$line],
            'FeeInfo' => [],
            'OptionUserDefined' => $this->amountFormatConfig->getOptionUserDefined((int) $order->getStoreId()),
            'ClockInfos' => [],
        ];
        OrgInvoiceReferenceNormalizer::applyOrgFields($payload, $origin);

        return $this->issueRequestFactory->create()
            ->setOrderId((int) $order->getEntityId())
            ->setOrderIncrementId((string) $order->getIncrementId())
            ->setStoreId((int) $order->getStoreId())
            ->setPayload($payload);
    }

    private function buildRefId(OrderInterface $order, int $refRevision): string
    {
        $seed = sprintf(
            'secomm-einvoice-cktm:%s:%s:%d',
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

    private function formatVatRateName(float $taxPercent): string
    {
        if ((float) (int) $taxPercent === $taxPercent) {
            return (string) ((int) $taxPercent) . '%';
        }

        return rtrim(rtrim(sprintf('%.4F', $taxPercent), '0'), '.') . '%';
    }

}
