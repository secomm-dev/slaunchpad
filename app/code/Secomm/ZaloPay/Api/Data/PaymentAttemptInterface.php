<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Api\Data;

/**
 * ZaloPay payment-first initiation attempt.
 *
 * Maps one ZaloPay provider transaction (app_trans_id) to the Quote it was
 * created against, BEFORE any Magento order exists. The lifecycle is an
 * explicit state machine — see the STATUS_* constants; every transition is
 * guarded in \Secomm\ZaloPay\Model\PaymentAttempt and illegal transitions
 * throw instead of silently overwriting state.
 */
interface PaymentAttemptInterface
{
    public const ENTITY_ID = 'entity_id';
    public const QUOTE_ID = 'quote_id';
    public const RESERVED_ORDER_ID = 'reserved_order_id';
    public const APP_TRANS_ID = 'app_trans_id';
    public const PROVIDER_TRANSACTION_ID = 'provider_transaction_id';
    public const PAY_URL = 'pay_url';
    public const PROVIDER_STATUS = 'provider_status';
    public const PAYMENT_STATUS = 'payment_status';
    public const AMOUNT = 'amount';
    public const CURRENCY = 'currency';
    public const CONTRACT_HASH = 'contract_hash';
    public const ORDER_ID = 'order_id';
    public const LAST_ERROR = 'last_error';
    public const REQ_RECONCILIATION = 'requires_reconciliation';
    public const RECONCILIATION_CODE = 'reconciliation_code';
    public const RECOVERY_ATTEMPTS = 'recovery_attempts';
    public const RECOVERY_EXHAUSTED = 'recovery_exhausted';
    public const RETRY_COUNT = 'retry_count';
    public const STORE_ID = 'store_id';
    /**
     * In-flight claim token (unix ts) for the order confirmation email
     * dispatch: the attempt row is FOR UPDATE-locked during finalization, so
     * a conditional claim update inside that transaction serializes
     * concurrent finalizers; a claim older than the reclaim grace may be
     * taken over by a later finalize driver (crashed sender recovery).
     */
    public const EMAIL_DISPATCH = 'email_dispatch';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
    public const EXPIRES_AT = 'expires_at';

    /**
     * Machine-readable reasons for REQ_RECONCILIATION (never parsed from
     * free-text last_error): the provider holds money but an automatic
     * Sales Order is structurally impossible — only an explicit manual/
     * reconciliation workflow may clear the flag. Corrective round 4 adds
     * provider_state_conflict (PAID vs authoritative failure),
     * late_paid_terminal_state (authoritative PAID on a terminal unpaid
     * attempt) and provider_transaction_unavailable (verified money with
     * no provable zp_trans_id).
     */
    public const RECON_AMOUNT_MISMATCH = 'amount_mismatch';
    public const RECON_CONTRACT_MISMATCH = 'contract_mismatch';
    public const RECON_PROVIDER_TX_CONFLICT = 'provider_transaction_conflict';
    public const RECON_AMOUNT_UNAVAILABLE = 'amount_unavailable';
    public const RECON_PROVIDER_STATE_CONFLICT = 'provider_state_conflict';
    public const RECON_LATE_PAID_TERMINAL_STATE = 'late_paid_terminal_state';
    public const RECON_PROVIDER_TX_UNAVAILABLE = 'provider_transaction_unavailable';

    /** Provider-side currency of the snapshot amount (ZaloPay only accepts VND). */
    public const CURRENCY_VND = 'VND';

    /** Attempt row created; provider transaction not yet created. */
    public const STATUS_INITIATED = 'initiated';
    /** Provider transaction created and pay URL stored; usable for redirect. */
    public const STATUS_ACTIVE = 'active';
    /** Provider confirmed payment; Magento order not yet bound. */
    public const STATUS_PAID = 'paid';
    /** Magento order placed and bound (terminal success). */
    public const STATUS_FINALIZED = 'finalized';
    /** Provider declined / errored (terminal). */
    public const STATUS_FAILED = 'failed';
    /** Superseded by a newer attempt — snapshot no longer matches the quote (terminal). */
    public const STATUS_STALE = 'stale';
    /** TTL elapsed with no provider outcome (terminal). */
    public const STATUS_EXPIRED = 'expired';

    /**
     * Get the attempt row id.
     *
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * Get the quote the attempt was created against.
     *
     * @return int
     */
    public function getQuoteId(): int;

    /**
     * Set the originating quote id.
     *
     * @param int $quoteId
     * @return void
     */
    public function setQuoteId(int $quoteId): void;

    /**
     * Get the order increment id reserved for this attempt.
     *
     * @return string
     */
    public function getReservedOrderId(): string;

    /**
     * Set the reserved order increment id.
     *
     * @param string $reservedOrderId
     * @return void
     */
    public function setReservedOrderId(string $reservedOrderId): void;

    /**
     * Get the ZaloPay app_trans_id (provider transaction reference).
     *
     * @return string|null
     */
    public function getAppTransId(): ?string;

    /**
     * Set the ZaloPay app_trans_id.
     *
     * @param string|null $appTransId
     * @return void
     */
    public function setAppTransId(?string $appTransId): void;

    /**
     * Get the ZaloPay zp_trans_id of the confirmed transaction.
     *
     * @return string|null
     */
    public function getProviderTransactionId(): ?string;

    /**
     * Set the ZaloPay zp_trans_id.
     *
     * @param string|null $providerTransactionId
     * @return void
     */
    public function setProviderTransactionId(?string $providerTransactionId): void;

    /**
     * Get the hosted-page pay URL stored at activation.
     *
     * @return string|null
     */
    public function getPayUrl(): ?string;

    /**
     * Set the hosted-page pay URL.
     *
     * @param string|null $payUrl
     * @return void
     */
    public function setPayUrl(?string $payUrl): void;

    /**
     * Get the last provider-side status string.
     *
     * @return string|null
     */
    public function getProviderStatus(): ?string;

    /**
     * Set the provider-side status string.
     *
     * @param string|null $providerStatus
     * @return void
     */
    public function setProviderStatus(?string $providerStatus): void;

    /**
     * Get the lifecycle status (one of the STATUS_* constants).
     *
     * @return string
     */
    public function getPaymentStatus(): string;

    /**
     * Raw status setter.
     *
     * Prefer the explicit mark*() transitions on
     * \Secomm\ZaloPay\Model\PaymentAttempt — this exists for persistence only.
     *
     * @param string $paymentStatus
     * @return void
     */
    public function setPaymentStatus(string $paymentStatus): void;

    /**
     * Get the snapshot amount in VND.
     *
     * Locked at initiation.
     *
     * @return int
     */
    public function getAmount(): int;

    /**
     * Set the snapshot amount in VND.
     *
     * @param int $amount
     * @return void
     */
    public function setAmount(int $amount): void;

    /**
     * Get the snapshot currency (CURRENCY_VND).
     *
     * @return string
     */
    public function getCurrency(): string;

    /**
     * Set the snapshot currency.
     *
     * @param string $currency
     * @return void
     */
    public function setCurrency(string $currency): void;

    /**
     * Get the quote payment-contract fingerprint.
     *
     * Sha-256 of the contract this attempt was created against
     * (see \Secomm\ZaloPay\Model\QuoteContractFingerprint). The order may
     * only be auto-created while the CURRENT quote still matches it.
     *
     * @return string|null
     */
    public function getContractHash(): ?string;

    /**
     * Set the quote payment-contract fingerprint.
     *
     * @param string|null $contractHash
     * @return void
     */
    public function setContractHash(?string $contractHash): void;

    /**
     * Get the bound Magento order id (null until finalized).
     *
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * Set the bound Magento order id.
     *
     * @param int|null $orderId
     * @return void
     */
    public function setOrderId(?int $orderId): void;

    /**
     * Get the last recorded error reason.
     *
     * @return string|null
     */
    public function getLastError(): ?string;

    /**
     * Set the last error reason.
     *
     * @param string|null $lastError
     * @return void
     */
    public function setLastError(?string $lastError): void;

    /**
     * Whether the attempt is quarantined: money-real but NOT auto-finalizable.
     *
     * Only an explicit manual/reconciliation workflow may clear this — no
     * generic callback, return or recovery query may finalize the attempt
     * while it is set.
     *
     * @return bool
     */
    public function getRequiresReconciliation(): bool;

    /**
     * Set the reconciliation quarantine flag.
     *
     * @param bool $requiresReconciliation
     * @return void
     */
    public function setRequiresReconciliation(bool $requiresReconciliation): void;

    /**
     * Get the machine-readable reconciliation reason (a RECON_* constant).
     *
     * @return string|null
     */
    public function getReconciliationCode(): ?string;

    /**
     * Set the machine-readable reconciliation reason.
     *
     * @param string|null $reconciliationCode
     * @return void
     */
    public function setReconciliationCode(?string $reconciliationCode): void;

    /**
     * Get how many bounded proactive (lost-callback) queries this attempt
     * already consumed.
     *
     * @return int
     */
    public function getRecoveryAttempts(): int;

    /**
     * Set the proactive recovery query attempt count.
     *
     * @param int $recoveryAttempts
     * @return void
     */
    public function setRecoveryAttempts(int $recoveryAttempts): void;

    /**
     * Whether the bounded recovery worker consumed its full proactive query
     * budget for this attempt (corrective round 4, Blocker 4).
     *
     * OPERATIONAL marker only — exhaustion is NOT evidence that the provider
     * took money and never quarantines the attempt: a valid authenticated
     * callback may still resolve the payment normally.
     *
     * @return bool
     */
    public function getRecoveryExhausted(): bool;

    /**
     * Set the explicit recovery-exhaustion marker.
     *
     * @param bool $recoveryExhausted
     * @return void
     */
    public function setRecoveryExhausted(bool $recoveryExhausted): void;

    /**
     * Get how many earlier attempts this quote superseded.
     *
     * @return int
     */
    public function getRetryCount(): int;

    /**
     * Set the retry count.
     *
     * @param int $retryCount
     * @return void
     */
    public function setRetryCount(int $retryCount): void;

    /**
     * Get the owning store id.
     *
     * @return int
     */
    public function getStoreId(): int;

    /**
     * Set the owning store id.
     *
     * @param int $storeId
     * @return void
     */
    public function setStoreId(int $storeId): void;

    /**
     * Get the creation timestamp.
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Set the creation timestamp.
     *
     * @param string|null $createdAt
     * @return void
     */
    public function setCreatedAt(?string $createdAt): void;

    /**
     * Get the last-update timestamp.
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * Set the last-update timestamp.
     *
     * @param string|null $updatedAt
     * @return void
     */
    public function setUpdatedAt(?string $updatedAt): void;

    /**
     * Get the TTL expiry timestamp.
     *
     * @return string|null
     */
    public function getExpiresAt(): ?string;

    /**
     * Set the TTL expiry timestamp.
     *
     * @param string|null $expiresAt
     * @return void
     */
    public function setExpiresAt(?string $expiresAt): void;

    // ---- Lifecycle state machine (explicit transitions) ----

    /**
     * Whether the attempt may still move to the given status.
     *
     * @param string $status
     * @return bool
     */
    public function canTransitionTo(string $status): bool;

    /**
     * Whether the attempt is in a terminal lifecycle state.
     *
     * @return bool
     */
    public function isTerminal(): bool;

    /**
     * Whether this attempt may be reused for a fresh Start redirect.
     *
     * ACTIVE with a stored pay URL and TTL not elapsed. A PAID/FINALIZED
     * transaction is never reused as a new attempt.
     *
     * @param string|null $now
     * @return bool
     */
    public function isReusable(?string $now = null): bool;

    /**
     * Whether the attempt TTL has elapsed.
     *
     * @param string|null $now
     * @return bool
     */
    public function isExpired(?string $now = null): bool;

    /**
     * INITIATED -> ACTIVE.
     *
     * Provider transaction created, pay URL stored.
     *
     * @param string $payUrl
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException On illegal transition.
     */
    public function markActive(string $payUrl): static;

    /**
     * INITIATED|ACTIVE -> FAILED.
     *
     * Provider declined or errored.
     *
     * @param string $errorMessage
     * @param string|null $providerStatus
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException On illegal transition.
     */
    public function markFailed(string $errorMessage, ?string $providerStatus = 'failed'): static;

    /**
     * INITIATED|ACTIVE -> STALE.
     *
     * Superseded (quote total changed / new attempt).
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException On illegal transition.
     */
    public function markStale(): static;

    /**
     * ACTIVE -> PAID.
     *
     * Provider confirmed payment; Magento order still unbound.
     *
     * @param string|null $providerTransactionId
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException On illegal transition.
     */
    public function markPaid(?string $providerTransactionId = null): static;

    /**
     * ACTIVE|PAID -> FINALIZED.
     *
     * Magento order placed and bound (terminal).
     *
     * @param int $orderId
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException On illegal transition.
     */
    public function markFinalized(int $orderId): static;
}
