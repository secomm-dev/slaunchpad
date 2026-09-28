<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Cod\Api;

/**
 * TASK-DFGFZ9 phase 3 (DEC-TASKDFGFZ9-003) — the COD collection ledger API. Carriers REPORT
 * their attempts here (never decide from it); the resolver READS prior/frozen collections
 * from it. Because every carrier must report before the provider POST, and the resolver
 * consults the ledger itself, no caller can bypass the P1 one-collection-per-order rule.
 *
 * Ledger rows NEVER reconcile money: provider acceptance of a COD order does not mean
 * Magento received it — never mark an order paid from ledger state.
 */
interface CodCollectionLedgerInterface
{
    /**
     * Arms (or refreshes) the PENDING row for this attempt. INSERT-FIRST: the per-order
     * claim (`active_order_claim` UNIQUE) is decided by the engine — a concurrent
     * different-attempt insert for the same order loses and this method throws
     * {@see \Secomm\Cod\Model\CodClaimConflictException}. For OUR OWN key the row is
     * re-armed: SUBMITTED|RECOVERED → no-op (never downgrade a submitted outcome); else
     * refresh amount/currency and restore status=PENDING. Callers invoke this ONLY for
     * amount > 0 — non-COD and zero-amount orders never enter the ledger.
     *
     * @throws \Secomm\Cod\Model\CodClaimConflictException when a DIFFERENT
     *         (carrier, reference) holds this order's active claim
     */
    public function recordPending(
        CodCollectionAttemptInterface $attempt,
        int $orderId,
        float $amount,
        string $currency
    ): void;

    /**
     * Marks the attempt SUBMITTED — or RECOVERED when the provider order already existed
     * (GHTK ORDER_ID_EXIST path). Guard: never overwrites a SUBMITTED row.
     */
    public function markSubmitted(CodCollectionAttemptInterface $attempt, bool $recovered): void;

    /**
     * Closes a non-submitted attempt: FAILED (definitive provider rejection — RELEASES the
     * order's claim: collected nothing, so other attempts may try again) or UNKNOWN (result
     * uncertain — the claim stays held until reconciled). InvalidArgumentException on any
     * other status. Guard: never touches a SUBMITTED|RECOVERED row.
     */
    public function markNotSubmitted(CodCollectionAttemptInterface $attempt, string $status, string $reasonCode): void;

    /**
     * The frozen decision of THIS attempt (same carrier_code + provider_reference):
     * amount > 0 and status != FAILED. A retry must replay this amount verbatim instead of
     * re-deciding (persisted-wins). A FAILED row is excluded — a definitive provider rejection
     * never collected anything, so the next attempt decides fresh.
     *
     * @return array{amount: string, currency: string}|null thin DB row (house convention)
     */
    public function findFrozenAmount(CodCollectionAttemptInterface $attempt): ?array;

    /**
     * The oldest prior collection of this order through a DIFFERENT (carrier_code,
     * provider_reference): amount > 0 and status != FAILED. A definitive rejection (FAILED)
     * never blocks; PENDING/SUBMITTED/RECOVERED/UNKNOWN all block conservatively. When
     * `$excludeAttempt` is null NOTHING is excluded (fail-safe — a caller that cannot name
     * its attempt is still held to the one-collection rule).
     *
     * @return array{carrier_code: string, provider_reference: string, amount: string, currency: string}|null
     */
    public function findCollectedPrior(int $orderId, ?CodCollectionAttemptInterface $excludeAttempt): ?array;
}
