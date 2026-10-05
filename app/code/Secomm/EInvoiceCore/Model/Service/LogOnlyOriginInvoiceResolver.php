<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Model\Service;

use Secomm\EInvoiceCore\Api\OriginInvoiceResolverInterface;
use Secomm\EInvoiceLog\Model\IssueLog;

/**
 * Fallback org resolver using only persisted issue log fields.
 */
class LogOnlyOriginInvoiceResolver implements OriginInvoiceResolverInterface
{
    public function resolve(IssueLog $originLog): array
    {
        return [
            'transaction_id' => (string) $originLog->getData('transaction_id'),
            'ref_id' => (string) $originLog->getData('ref_id'),
            'inv_series' => (string) $originLog->getData('inv_series'),
            'inv_date' => substr((string) ($originLog->getData('issued_at') ?: ''), 0, 10),
            'inv_no' => (string) ($originLog->getData('inv_no') ?? ''),
            'template_no' => (string) $originLog->getData('invoice_template_id'),
        ];
    }
}
