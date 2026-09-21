<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Http;

use Secomm\ShippingCore\Api\Http\CarrierHttpException;

/**
 * TASK-7AJ3K8 — executes an operation under a {@see RetryPolicy}. Immediate rethrow on any
 * non-retryable category or once the attempt budget is exhausted; the LAST exception always
 * propagates (never swallowed, never wrapped — the caller's error surface stays intact).
 */
final class RetryExecutor
{
    /**
     * @param callable(): mixed $operation
     * @return mixed the operation's own return value
     * @throws CarrierHttpException the final failure, per the policy
     */
    public function execute(callable $operation, RetryPolicy $policy): mixed
    {
        $attempt = 0;
        while (true) {
            try {
                return $operation();
            } catch (CarrierHttpException $e) {
                $attempt++;
                if (!$policy->isRetryable($e->getCategory()) || $attempt >= $policy->getMaxAttempts()) {
                    throw $e;
                }
            }
        }
    }
}
