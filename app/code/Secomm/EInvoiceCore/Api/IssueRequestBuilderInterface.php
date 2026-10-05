<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Api;

use Magento\Framework\Exception\LocalizedException;
use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;

/**
 * Builds a canonical issue request for a sales order (implemented by provider bridge).
 *
 * @api
 * @since 1.0.0
 */
interface IssueRequestBuilderInterface
{
    /**
     * Build issue request for the given order.
     *
     * @param int $orderId
     * @param array<string, mixed> $context Provider-specific context (e.g. template_id).
     * @return \Secomm\EInvoiceCore\Api\Data\IssueRequestInterface
     * @throws LocalizedException
     */
    public function build(int $orderId, array $context = []): IssueRequestInterface;
}
