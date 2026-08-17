<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model;

/**
 * Thrown by GhtkApiClient on non-recoverable failure. $retryable distinguishes
 * network/5xx (eligible for the configured retry) from 4xx (never retried).
 */
class GhtkApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private bool $retryable = false,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
