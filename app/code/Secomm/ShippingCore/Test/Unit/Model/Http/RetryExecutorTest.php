<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Http;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Http\CarrierHttpException;
use Secomm\ShippingCore\Api\Http\CarrierHttpErrorCategory;
use Secomm\ShippingCore\Model\Http\RetryExecutor;
use Secomm\ShippingCore\Model\Http\RetryPolicy;

/**
 * TASK-7AJ3K8 — retry policy semantics: maxAttempts counts TOTAL attempts; only categories in
 * the policy retry; the LAST exception always propagates unchanged.
 */
class RetryExecutorTest extends TestCase
{
    private RetryExecutor $executor;

    protected function setUp(): void
    {
        $this->executor = new RetryExecutor();
    }

    public function testSingleAttemptPolicyNeverRetries(): void
    {
        $calls = 0;
        $operation = function () use (&$calls): void {
            $calls++;
            throw new CarrierHttpException(CarrierHttpErrorCategory::NETWORK, 'down');
        };

        try {
            $this->executor->execute($operation, RetryPolicy::singleAttempt());
            $this->fail('Expected CarrierHttpException');
        } catch (CarrierHttpException $e) {
            $this->assertSame(CarrierHttpErrorCategory::NETWORK, $e->getCategory());
        }

        $this->assertSame(1, $calls);
    }

    public function testRetryableCategoryRetriesUntilSuccess(): void
    {
        $calls = 0;
        $operation = function () use (&$calls): string {
            $calls++;
            if ($calls < 3) {
                throw new CarrierHttpException(CarrierHttpErrorCategory::SERVER_ERROR, 'flaky');
            }

            return 'ok';
        };

        $result = $this->executor->execute($operation, RetryPolicy::safeRead(3));

        $this->assertSame('ok', $result);
        $this->assertSame(3, $calls);
    }

    public function testExhaustedBudgetThrowsTheLastException(): void
    {
        $calls = 0;
        $operation = function () use (&$calls): void {
            $calls++;
            throw new CarrierHttpException(CarrierHttpErrorCategory::NETWORK, 'still down ' . $calls);
        };

        try {
            $this->executor->execute($operation, RetryPolicy::safeRead(2));
            $this->fail('Expected CarrierHttpException');
        } catch (CarrierHttpException $e) {
            $this->assertSame('still down 2', $e->getMessage());
        }

        $this->assertSame(2, $calls);
    }

    public function testNonRetryableCategoryThrowsImmediately(): void
    {
        $calls = 0;
        $operation = function () use (&$calls): void {
            $calls++;
            throw new CarrierHttpException(CarrierHttpErrorCategory::CLIENT_ERROR, 'bad request');
        };

        try {
            $this->executor->execute($operation, RetryPolicy::safeRead(5));
            $this->fail('Expected CarrierHttpException');
        } catch (CarrierHttpException $e) {
            $this->assertSame(CarrierHttpErrorCategory::CLIENT_ERROR, $e->getCategory());
        }

        $this->assertSame(1, $calls);
    }

    public function testRateLimitIsNeverRetryableUnderSafeRead(): void
    {
        $calls = 0;
        $operation = function () use (&$calls): void {
            $calls++;
            throw new CarrierHttpException(CarrierHttpErrorCategory::RATE_LIMIT, 'slow down');
        };

        try {
            $this->executor->execute($operation, RetryPolicy::safeRead(5));
            $this->fail('Expected CarrierHttpException');
        } catch (CarrierHttpException) {
            // classified, immediate rethrow
        }

        $this->assertSame(1, $calls);
    }

    public function testPolicyRequiresAtLeastOneAttempt(): void
    {
        $this->expectException(\LogicException::class);
        new RetryPolicy(0, []);
    }

    public function testSafeReadCoversNetworkServerErrorAndTimeout(): void
    {
        $policy = RetryPolicy::safeRead(4);

        $this->assertSame(4, $policy->getMaxAttempts());
        $this->assertTrue($policy->isRetryable(CarrierHttpErrorCategory::NETWORK));
        $this->assertTrue($policy->isRetryable(CarrierHttpErrorCategory::SERVER_ERROR));
        $this->assertTrue($policy->isRetryable(CarrierHttpErrorCategory::TIMEOUT));
        $this->assertFalse($policy->isRetryable(CarrierHttpErrorCategory::CLIENT_ERROR));
        $this->assertFalse($policy->isRetryable(CarrierHttpErrorCategory::INVALID_RESPONSE));
    }
}
