<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Api;

use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;
use Secomm\EInvoiceCore\Api\Data\IssueResultInterface;

/**
 * Provider-agnostic electronic invoice issuer.
 *
 * @api
 * @since 1.0.0
 */
interface InvoiceIssuerInterface
{
    /**
     * Submit invoice issuance to external provider.
     *
     * @param \Secomm\EInvoiceCore\Api\Data\IssueRequestInterface $request
     * @return \Secomm\EInvoiceCore\Api\Data\IssueResultInterface
     */
    public function issue(IssueRequestInterface $request): IssueResultInterface;
}
