<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Api;

use Magento\Framework\Exception\LocalizedException;
use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;

/**
 * Builds an adjustment issue request for a credit memo (implemented per provider).
 *
 * @api
 * @since 1.0.0
 */
interface AdjustmentRequestBuilderInterface
{
    /**
     * Build an adjustment issue request for the given credit memo.
     *
     * @param int $creditmemoId
     * @param array<string, mixed> $context Provider-specific context (template_id, origin meta).
     * @return \Secomm\EInvoiceCore\Api\Data\IssueRequestInterface
     * @throws LocalizedException
     */
    public function build(int $creditmemoId, array $context = []): IssueRequestInterface;
}
