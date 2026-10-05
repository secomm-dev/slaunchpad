<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Model\Service;

/**
 * Controls when publish may be retried after known provider error codes.
 */
class PublishRetryPolicy
{
    private const NON_RETRYABLE_CODES = [
        'InvoiceDuplicated',
        'InvoiceNumberNotContinuous',
    ];

    public function mayRetry(string $errorMessage, bool $hasNewRefRevision): bool
    {
        foreach (self::NON_RETRYABLE_CODES as $code) {
            if (stripos($errorMessage, $code) !== false) {
                return $hasNewRefRevision;
            }
        }

        return true;
    }
}
