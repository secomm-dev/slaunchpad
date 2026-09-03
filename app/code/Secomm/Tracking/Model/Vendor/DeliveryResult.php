<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model\Vendor;

/**
 * FEAT-31X6N2 — outcome of one vendor send attempt. responseSummary must be
 * masked before it reaches any log (spec §10).
 */
final class DeliveryResult
{
    /**
     * @param int|null $httpStatus Null when the call was skipped before HTTP.
     * @param string|null $responseSummary Masked vendor response summary.
     * @param bool $retryable HTTP 5xx / transport errors — backoff applies.
     */
    public function __construct(
        public readonly ?int $httpStatus,
        public readonly ?string $responseSummary,
        public readonly bool $retryable
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->httpStatus !== null && $this->httpStatus >= 200 && $this->httpStatus < 300;
    }
}
