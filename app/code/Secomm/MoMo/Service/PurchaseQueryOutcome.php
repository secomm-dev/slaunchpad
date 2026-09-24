<?php
/**
 * Purchase-query outcome value object (MOMO-04).
 *
 * The immutable result of classifying one MoMo v2/query resultCode. The
 * category is the ONLY payment-state input both authoritative purchase-query
 * callers act on (browser Return — MOMO-01/04; proactive recovery — MOMO-03);
 * the parsed code and the ambiguity reason exist for diagnostic logging.
 *
 * @author    Secomm Team
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Service;

class PurchaseQueryOutcome
{
    /**
     * Documented final PAID outcome (0 = successful; 9000 = authorized —
     * for the module's 1-step captureWallet / default autoCapture=true
     * contract MoMo documents "mark this transaction as success").
     */
    public const PAID = 'paid';

    /**
     * Documented NON-FINAL transaction state (1000 initiated, 7000 not yet
     * paid, 7002 still processing): money may still move.
     */
    public const PENDING = 'pending';

    /**
     * Documented FINAL payment-transaction failure — the only category that
     * may terminally fail an attempt.
     */
    public const FINAL_FAILURE = 'final_failure';

    /**
     * NO payment-state conclusion possible: request/system codes, a missing
     * or unparseable resultCode, or any unmapped code. Must never mutate
     * money state — unknown defaults to ambiguous, never FAILED.
     */
    public const AMBIGUOUS = 'ambiguous';

    /** AMBIGUOUS reason: the response carried no parseable resultCode. */
    public const REASON_UNPARSEABLE = 'unparseable_result_code';

    /** AMBIGUOUS reason: request/system-level code — not a transaction outcome. */
    public const REASON_REQUEST_SYSTEM = 'request_system_code';

    /** AMBIGUOUS reason: parseable but not in any documented allowlist. */
    public const REASON_UNMAPPED = 'unmapped_code';

    /**
     * @param string $category One of the category constants.
     * @param int|null $resultCode Parsed resultCode; null only when the
     *        response carried no parseable code (AMBIGUOUS/unparseable).
     * @param string|null $reason AMBIGUOUS sub-reason; null for the three
     *        documented categories.
     */
    public function __construct(
        private readonly string $category,
        private readonly ?int $resultCode,
        private readonly ?string $reason = null
    ) {
    }

    /**
     * Outcome category — the caller's routing key.
     *
     * @return string
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * Parsed resultCode for diagnostics; null only for unparseable responses.
     *
     * @return int|null
     */
    public function getResultCode(): ?int
    {
        return $this->resultCode;
    }

    /**
     * AMBIGUOUS sub-reason (diagnostics); null for documented categories.
     *
     * @return string|null
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }
}
