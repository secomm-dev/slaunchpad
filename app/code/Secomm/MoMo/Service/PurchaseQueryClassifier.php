<?php
/**
 * Single source of truth for classifying MoMo v2/query resultCodes (MOMO-04).
 *
 * Both authoritative PURCHASE-query callers share this classifier — the
 * browser Return path (ReturnProcessor, MOMO-01/04) and the proactive
 * recovery cron (PaymentRecovery, MOMO-03) — so the outcome semantics can
 * never drift between the two paths. Allowlists follow the provider-documented
 * result-code contract (developers.momo.vn, verified 2026-09-21 during the
 * MOMO-03 correction rounds):
 *
 *  - PAID: 0 (successful), 9000 (authorized — Final Status = No, but for the
 *    module's 1-step captureWallet / default autoCapture=true purchase
 *    contract MoMo documents "mark this transaction as success"). Both still
 *    pass the amount + positive-transId + identity guards in the callers
 *    before any order.
 *  - PENDING: 1000 (initiated, waiting for user confirmation), 7000 (not yet
 *    paid), 7002 (still processing) — Final Status = No, money may still move.
 *  - FINAL_FAILURE: the documented FINAL payment-transaction failures
 *    (Final Status = Yes) — the ONLY codes allowed to terminally fail an
 *    attempt.
 *  - AMBIGUOUS: request/system codes (Final Status = No — request-level, not
 *    transaction outcomes), a missing or unparseable resultCode, and ANY
 *    unmapped code. Unknown defaults to ambiguous, NEVER failed (fail-safe
 *    money state).
 *
 * Deliberately NOT shared with the refund path: refund semantics differ
 * (notably 9000), see RefundResultClassifier.
 *
 * @author    Secomm Team
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Service;

class PurchaseQueryClassifier
{
    /**
     * Authoritative PAID codes (v2/query).
     *
     * @var int[]
     */
    private const QUERY_PAID = [0, 9000];

    /**
     * Documented NON-FINAL transaction states (Final Status = No).
     *
     * @var int[]
     */
    private const QUERY_PENDING = [1000, 7000, 7002];

    /**
     * Documented FINAL payment-transaction failures (Final Status = Yes) —
     * the only codes a caller may act on as a failure proof.
     *
     * @var int[]
     */
    private const QUERY_FAILURE = [
        98, 99,
        1001, 1002, 1003, 1004, 1005, 1006, 1007, 1017, 1026,
        2019, 4001, 4002, 4100,
    ];

    /**
     * Documented REQUEST/SYSTEM errors (Final Status = No — request-level,
     * not transaction outcomes). Money state is unknown; ambiguous.
     *
     * @var int[]
     */
    private const QUERY_REQUEST_ERROR = [10, 11, 12, 13, 20, 21, 22, 40, 41, 42, 43, 45, 47];

    /**
     * Classify one raw v2/query resultCode into the fail-safe outcome.
     *
     * @param mixed $rawResultCode The untrusted response field, as received.
     * @return PurchaseQueryOutcome
     */
    public function classify(mixed $rawResultCode): PurchaseQueryOutcome
    {
        // AC5/AC6: a response without a parseable integer resultCode cannot
        // support ANY payment-state decision — ambiguous, never a failure.
        $resultCode = is_scalar($rawResultCode) && preg_match('/^-?\d+$/', trim((string)$rawResultCode)) === 1
            ? (int)$rawResultCode
            : null;
        if ($resultCode === null) {
            return new PurchaseQueryOutcome(
                PurchaseQueryOutcome::AMBIGUOUS,
                null,
                PurchaseQueryOutcome::REASON_UNPARSEABLE
            );
        }

        if (in_array($resultCode, self::QUERY_PAID, true)) {
            return new PurchaseQueryOutcome(PurchaseQueryOutcome::PAID, $resultCode);
        }

        if (in_array($resultCode, self::QUERY_PENDING, true)) {
            return new PurchaseQueryOutcome(PurchaseQueryOutcome::PENDING, $resultCode);
        }

        if (in_array($resultCode, self::QUERY_REQUEST_ERROR, true)) {
            // Request/system-level: not a transaction outcome — money state
            // unknown, never a failure proof.
            return new PurchaseQueryOutcome(
                PurchaseQueryOutcome::AMBIGUOUS,
                $resultCode,
                PurchaseQueryOutcome::REASON_REQUEST_SYSTEM
            );
        }

        if (in_array($resultCode, self::QUERY_FAILURE, true)) {
            // Documented FINAL failure — the ONLY branch that authorizes a
            // terminal failure mutation in the callers.
            return new PurchaseQueryOutcome(PurchaseQueryOutcome::FINAL_FAILURE, $resultCode);
        }

        // Fail-safe default: an unmapped/undocumented code is NOT a failure
        // proof — ambiguous, no mutation.
        return new PurchaseQueryOutcome(
            PurchaseQueryOutcome::AMBIGUOUS,
            $resultCode,
            PurchaseQueryOutcome::REASON_UNMAPPED
        );
    }
}
