<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Mapper;

/**
 * Normalizes original-invoice reference fields per MeInvoice §10 (ND123).
 */
class OrgInvoiceReferenceNormalizer
{
    public const ORG_INVOICE_TYPE_ND123 = 1;
    public const ORG_INVOICE_TYPE_ND51 = 3;
    /**
     * Split full InvSeries (e.g. 1C24MAA) into OrgInvTemplateNo + OrgInvSeries (C24MAA).
     *
     * @return array{template_no: string, series: string}
     */
    public static function splitInvSeries(string $fullInvSeries): array
    {
        if ($fullInvSeries === '') {
            return ['template_no' => '', 'series' => ''];
        }

        if (strlen($fullInvSeries) >= 7) {
            return [
                'template_no' => substr($fullInvSeries, 0, 1),
                'series' => substr($fullInvSeries, -6),
            ];
        }

        return ['template_no' => '', 'series' => $fullInvSeries];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $origin
     */
    public static function applyOrgFields(array &$payload, array $origin): void
    {
        $split = self::splitInvSeries((string) ($origin['inv_series'] ?? ''));
        $payload['OrgInvoiceTransactionID'] = (string) ($origin['transaction_id'] ?? '');
        $payload['OrgRefID'] = (string) ($origin['ref_id'] ?? '');
        $payload['OrgInvNo'] = (string) ($origin['inv_no'] ?? '');
        $payload['OrgInvDate'] = (string) ($origin['inv_date'] ?? '');
        $payload['OrgInvSeries'] = $split['series'] !== ''
            ? $split['series']
            : (string) ($origin['inv_series'] ?? '');
        $payload['OrgInvTemplateNo'] = $split['template_no'] !== ''
            ? $split['template_no']
            : (string) ($origin['template_no'] ?? '');
        $payload['OrgInvoiceType'] = (int) ($origin['invoice_type'] ?? self::ORG_INVOICE_TYPE_ND123);
    }
}
