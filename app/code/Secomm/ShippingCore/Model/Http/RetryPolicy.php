<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Http;

use Secomm\ShippingCore\Api\Http\CarrierHttpErrorCategory;

/**
 * TASK-7AJ3K8 — immutable retry policy. maxAttempts counts TOTAL attempts (1 = single shot).
 *
 * Safety rule (DEC-TASK7AJ3K8-001 §1): only SAFE READ operations get a policy with retryable
 * categories (NETWORK/SERVER_ERROR/TIMEOUT); non-idempotent create operations run with a
 * single-attempt policy — the shared primitive never retries on its own judgment.
 */
final class RetryPolicy
{
    /** Single-attempt policy for non-idempotent operations (create shipment/order). */
    public static function singleAttempt(): self
    {
        return new self(1, []);
    }

    /**
     * @param int $maxAttempts total attempts, >= 1
     * @param string[] $retryableCategories CarrierHttpErrorCategory constants worth retrying
     */
    public function __construct(
        private readonly int $maxAttempts,
        private readonly array $retryableCategories
    ) {
        if ($maxAttempts < 1) {
            throw new \LogicException('Retry policy requires at least one attempt.');
        }
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function isRetryable(string $category): bool
    {
        return in_array($category, $this->retryableCategories, true);
    }

    /** Convenience read policy: retry transport/5xx failures. */
    public static function safeRead(int $maxAttempts): self
    {
        return new self(
            max(1, $maxAttempts),
            [CarrierHttpErrorCategory::NETWORK, CarrierHttpErrorCategory::SERVER_ERROR, CarrierHttpErrorCategory::TIMEOUT]
        );
    }
}
