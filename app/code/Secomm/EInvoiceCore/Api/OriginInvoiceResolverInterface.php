<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Api;

use Secomm\EInvoiceLog\Model\IssueLog;

/**
 * Resolves original invoice org fields for adjustment/replace payloads.
 */
interface OriginInvoiceResolverInterface
{
    /**
     * @return array<string, string>
     */
    public function resolve(IssueLog $originLog): array;
}
