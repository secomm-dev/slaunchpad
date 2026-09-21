<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Api;

use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;

/**
 * Persistence and lookup contract for ZaloPay payment attempts.
 */
interface PaymentAttemptRepositoryInterface
{
    /**
     * Load one attempt by row id.
     *
     * @param int $entityId
     * @return PaymentAttemptInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function get(int $entityId): PaymentAttemptInterface;

    /**
     * Persist an attempt.
     *
     * @param PaymentAttemptInterface $attempt
     * @return PaymentAttemptInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(PaymentAttemptInterface $attempt): PaymentAttemptInterface;

    /**
     * Primary callback lookup: provider transaction reference -> attempt.
     *
     * @param string $appTransId
     * @return PaymentAttemptInterface|null
     */
    public function getByAppTransId(string $appTransId): ?PaymentAttemptInterface;

    /**
     * The newest non-terminal attempt that still owns the given quote.
     *
     * Covers STATUS_INITIATED (in-flight creation) and STATUS_ACTIVE.
     *
     * @param int $quoteId
     * @return PaymentAttemptInterface|null
     */
    public function getActiveByQuoteId(int $quoteId): ?PaymentAttemptInterface;

    /**
     * All attempts ever created for a quote, oldest first.
     *
     * @param int $quoteId
     * @return PaymentAttemptInterface[]
     */
    public function getListByQuoteId(int $quoteId): array;

    /**
     * The first attempt for a quote that BLOCKS a new provider transaction
     * (corrective round 4, Blocker 2): any attempt that is money-real or
     * quarantined — PAID, FINALIZED, or requires_reconciliation (structured
     * flags only, never last_error parsing).
     *
     * Bounded lookup (LIMIT 1 on the quote_id index) for the Start guard.
     *
     * @param int $quoteId
     * @return PaymentAttemptInterface|null
     */
    public function getBlockingAttemptByQuoteId(int $quoteId): ?PaymentAttemptInterface;

    /**
     * Row-lock (SELECT ... FOR UPDATE) lookup by app_trans_id.
     *
     * The caller MUST hold an open DB transaction for the lock to be
     * effective; the lock is held until that transaction commits or rolls
     * back.
     *
     * @param string $appTransId
     * @return PaymentAttemptInterface|null
     */
    public function lockByAppTransId(string $appTransId): ?PaymentAttemptInterface;

    /**
     * Atomically claim the order confirmation email dispatch for one attempt
     * (see PaymentAttemptResource::claimEmailDispatch). Must be called while
     * the attempt row is locked inside the caller's transaction so
     * concurrent finalizers serialize on the row lock.
     *
     * @param int $entityId
     * @param int $token Claim token (current unix ts).
     * @param int $graceSeconds Age at which an existing claim is reclaimable.
     * @return bool True if THIS call now holds the claim.
     */
    public function claimEmailDispatch(int $entityId, int $token, int $graceSeconds): bool;

    /**
     * Release THIS caller's email dispatch claim (token-guarded, non-fatal).
     *
     * @param int $entityId
     * @param int $token
     * @return void
     */
    public function releaseEmailDispatch(int $entityId, int $token): void;
}
