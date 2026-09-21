<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model;

/**
 * Thrown by GhtkApiClient on non-recoverable failure. $retryable distinguishes
 * network/5xx (eligible for the configured retry) from 4xx (never retried).
 *
 * TASK-W8SH0N — carries the shared transport category
 * (\Secomm\ShippingCore\Api\Http\CarrierHttpErrorCategory) so the RATE outcome
 * classifier can map it to TECHNICAL_FAILURE vs UNAVAILABLE without parsing
 * message strings. Optional (null on legacy/manual constructions).
 */
class GhtkApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private bool $retryable = false,
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly ?string $category = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    /** A CarrierHttpErrorCategory constant when raised from transport; null otherwise. */
    public function getCategory(): ?string
    {
        return $this->category;
    }
}
