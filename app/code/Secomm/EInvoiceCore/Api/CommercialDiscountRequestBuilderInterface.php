<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Api;

use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;

/**
 * Builds commercial discount (ReferenceType=5) issue requests.
 */
interface CommercialDiscountRequestBuilderInterface
{
    /**
     * @param int $orderId
     * @param array<string, string|float> $discount list_no, list_date, invoice_note, amount, vat_amount
     * @param array<string, mixed> $context origin, template_id, ref_revision
     */
    public function build(int $orderId, array $discount, array $context = []): IssueRequestInterface;
}
