<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Service;

use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Service\RefundOutcomeMarker;

/**
 * TASK-CG6BM7 corrective round 7 (F28): contract tests for the EXACT-
 * REFUND, ONE-SHOT, FAIL-CLOSED provider-skip marker.
 *
 * Pinned invariants:
 *  - authorize -> consume (true, exactly once) -> consume (false);
 *  - clear is idempotent and works from any state;
 *  - authorizations are keyed by CREDIT MEMO id: a second refund on the
 *    same order (a different credit memo) never inherits another refund's
 *    skip;
 *  - the cron-style finalize contract (authorize -> core accounting
 *    consumes inside -> finally clear) never leaks the pin, even when the
 *    accounting throws.
 */
class RefundOutcomeMarkerTest extends TestCase
{
    /**
     * The marker under test (plain in-memory state - no mocks needed).
     *
     * @var RefundOutcomeMarker
     */
    private RefundOutcomeMarker $marker;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->marker = new RefundOutcomeMarker();
    }

    /**
     * One-shot: the first consume returns true (and removes the pin), the
     * second consume for the same credit memo returns false.
     */
    public function testAuthorizeThenConsumeExactlyOnce(): void
    {
        self::assertFalse($this->marker->consume(9001), 'no authorization without authorize()');

        $this->marker->authorize(9001);

        self::assertTrue($this->marker->consume(9001), 'first consume consumes the authorization');
        self::assertFalse($this->marker->consume(9001), 'one-shot: second consume finds nothing');
    }

    /**
     * clear() is idempotent: callable without an authorization, after a
     * consume, and repeatedly - always leaving no pin behind.
     */
    public function testClearIsIdempotentFromAnyState(): void
    {
        $this->marker->clear(9002); // never authorized - must not error
        self::assertFalse($this->marker->consume(9002));

        $this->marker->authorize(9002);
        $this->marker->clear(9002);
        $this->marker->clear(9002); // double clear
        self::assertFalse($this->marker->consume(9002), 'cleared pin is not consumable');
    }

    /**
     * Marker isolation: two refunds on ONE order = two credit memos. Refund
     * B must not inherit refund A's authorization, and consuming A must not
     * touch B.
     */
    public function testAuthorizationsAreIsolatedPerCreditMemoOnSameOrder(): void
    {
        // Refund A (credit memo 100) is authorized for its core finalize.
        $this->marker->authorize(100);

        // Refund B (credit memo 101, SAME order 77, same PHP process) is a
        // different refund: it must NOT inherit A's provider-skip.
        self::assertFalse($this->marker->consume(101));

        // Consuming A's pin leaves B unaffected.
        self::assertTrue($this->marker->consume(100));
        self::assertFalse($this->marker->consume(101));

        // Refund B is then authorized and consumed independently.
        $this->marker->authorize(101);
        self::assertTrue($this->marker->consume(101));
        self::assertFalse($this->marker->consume(100), 'A stays consumed - no resurrect');
    }

    /**
     * The cron-style finalize contract (PendingRefundManager::finalizeSuccess
     * consumes this API): authorize -> refundOperation consumes inside ->
     * finally clear. When the accounting THROWS before consuming (or after),
     * the pin is dropped - a retry/reconciliation can never silently skip
     * the provider for other credit memos and the same credit memo cannot
     * double-consume.
     */
    public function testCronStyleFinalizeClearsAuthorizationOnFailure(): void
    {
        $consumedInside = null;
        $accounting = function (int $cmId) use (&$consumedInside): void {
            // Inside the core accounting, the gateway refund command
            // consumes the authorization (provider skip, exactly once).
            $consumedInside = $this->marker->consume($cmId);
        };

        $this->marker->authorize(9003);
        try {
            $accounting(9003);
            self::assertTrue($consumedInside, 'authorization consumed exactly once inside the finalize');
            $this->failingAccounting();
        } catch (\RuntimeException) {
            // the finalize failure propagates
        } finally {
            $this->marker->clear(9003);
        }

        self::assertFalse($this->marker->consume(9003), 'finally clear: no pin leaks past the failed finalize');
        self::assertFalse($this->marker->consume(9004), 'other credit memos were never authorized');
    }

    /**
     * Stands in for the local accounting step of the cron finalize: it
     * fails after the gateway skip was consumed inside.
     *
     * @return void
     * @throws \RuntimeException The simulated accounting failure.
     */
    private function failingAccounting(): void
    {
        throw new \RuntimeException('accounting boom');
    }

    /**
     * Success variant of the finalize contract: the pin is consumed inside
     * the accounting and the finally clear is a harmless no-op afterwards -
     * still exactly one skip per refund.
     */
    public function testCronStyleFinalizeSuccessLeavesNothingBehind(): void
    {
        $this->marker->authorize(9005);

        $consumed = $this->marker->consume(9005); // gateway skip inside accounting
        $this->marker->clear(9005);               // finally

        self::assertTrue($consumed);
        self::assertFalse($this->marker->consume(9005), 'no leak after a completed finalize');
    }

    /**
     * Re-authorizing the same credit memo is possible (a fresh refund
     * attempt on a NEW attempt lifecycle) but stays strictly one-shot per
     * authorization.
     */
    public function testReauthorizeStaysOneShotPerGrant(): void
    {
        $this->marker->authorize(9006);
        self::assertTrue($this->marker->consume(9006));

        $this->marker->authorize(9006); // authorize() is explicit, never implicit
        self::assertTrue($this->marker->consume(9006));
        self::assertFalse($this->marker->consume(9006));
    }
}
