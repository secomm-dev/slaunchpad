<?php
/**
 * Repository contract for MoMo payment attempts.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Secomm\MoMo\Api\Data\PaymentAttemptInterface;

interface PaymentAttemptRepositoryInterface
{
    /**
     * Persist the attempt.
     *
     * @param PaymentAttemptInterface $attempt
     * @return PaymentAttemptInterface
     * @throws LocalizedException
     */
    public function save(PaymentAttemptInterface $attempt): PaymentAttemptInterface;

    /**
     * Load by row id.
     *
     * @param int $entityId
     * @return PaymentAttemptInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $entityId): PaymentAttemptInterface;

    /**
     * Load by the MoMo orderId merchant reference.
     *
     * @param string $orderRef
     * @return PaymentAttemptInterface|null Null when no row matches.
     */
    public function getByOrderRef(string $orderRef): ?PaymentAttemptInterface;

    /**
     * SELECT ... FOR UPDATE by order_ref — the caller owns the surrounding
     * DB transaction (never opens one of its own, never commits).
     *
     * @param string $orderRef
     * @return PaymentAttemptInterface|null Null when no row matches.
     * @throws LocalizedException
     */
    public function lockByOrderRef(string $orderRef): ?PaymentAttemptInterface;

    /**
     * The quote's current non-terminal attempt (INITIATED/ACTIVE), null when none.
     *
     * @param int $quoteId
     * @return PaymentAttemptInterface|null
     */
    public function getActiveByQuoteId(int $quoteId): ?PaymentAttemptInterface;

    /**
     * Money-real/quarantined evidence for the quote (PAID, FINALIZED or
     * requires_reconciliation) — blocks a new provider transaction.
     *
     * @param int $quoteId
     * @return PaymentAttemptInterface|null
     */
    public function getBlockingAttemptByQuoteId(int $quoteId): ?PaymentAttemptInterface;

    /**
     * All attempts of a quote (history, for retry counts).
     *
     * @param int $quoteId
     * @return PaymentAttemptInterface[]
     */
    public function getListByQuoteId(int $quoteId): array;

    /**
     * Atomically claim the order confirmation email dispatch (in-flight
     * token). Serialized by the FOR UPDATE row lock held by the finalizer.
     *
     * @param int $entityId
     * @param int $token Unix ts of the claim.
     * @param int $grace Seconds after which an unreleased claim is reclaimable.
     * @return bool True when THIS caller holds the dispatch.
     */
    public function claimEmailDispatch(int $entityId, int $token, int $grace): bool;

    /**
     * Release an in-flight email dispatch claim (token must match).
     *
     * @param int $entityId
     * @param int $token
     * @return bool
     */
    public function releaseEmailDispatch(int $entityId, int $token): bool;
}
