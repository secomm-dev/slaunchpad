<?php
/**
 * Secomm MoMo payment-first initiation attempt.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Api\Data;

/**
 * MoMo payment attempt — one row per MoMo provider transaction created
 * against a Quote, BEFORE any Magento order exists.
 *
 * The lifecycle is an explicit state machine (see the STATUS_* constants);
 * every transition is guarded in \Secomm\MoMo\Model\PaymentAttempt and
 * illegal transitions throw instead of silently overwriting state.
 */
interface PaymentAttemptInterface
{
    public const ENTITY_ID = 'entity_id';
    public const QUOTE_ID = 'quote_id';
    public const RESERVED_ORDER_ID = 'reserved_order_id';
    /** MoMo `orderId` merchant reference (minted at initiation, unique). */
    public const ORDER_REF = 'order_ref';
    /** MoMo `requestId` of the create call (the v2/query identity). */
    public const REQUEST_ID = 'request_id';
    /** MoMo `transId` of the confirmed transaction. */
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
    public const RETRY_COUNT = 'retry_count';
    /** Proactive v2/query recovery budget consumed (MOMO-03, ZaloPay parity). */
    public const RECOVERY_ATTEMPTS = 'recovery_attempts';
    /**
     * Operational-only recovery marker: proactive queries stopped for this
     * attempt. NOT money-real evidence and NOT a quarantine — an
     * authenticated IPN/Return arriving later still resolves the payment
     * normally; only proactive queries stop.
     */
    public const RECOVERY_EXHAUSTED = 'recovery_exhausted';
    public const STORE_ID = 'store_id';
    /**
     * In-flight claim token (unix ts) for the order confirmation email
     * dispatch (NULL = none). Serialized by the FOR UPDATE row lock held
     * during finalization.
     */
    public const EMAIL_DISPATCH = 'email_dispatch';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
    public const EXPIRES_AT = 'expires_at';

    /**
     * Machine-readable reasons for REQ_RECONCILIATION (never parsed from
     * free-text last_error): the provider holds money but an automatic
     * Sales Order is structurally impossible — only an explicit manual
     * reconciliation workflow may clear the flag.
     */
    public const RECON_AMOUNT_MISMATCH = 'amount_mismatch';
    public const RECON_CONTRACT_MISMATCH = 'contract_mismatch';
    public const RECON_PROVIDER_TX_CONFLICT = 'provider_transaction_conflict';
    public const RECON_PROVIDER_STATE_CONFLICT = 'provider_state_conflict';
    public const RECON_LATE_PAID_TERMINAL_STATE = 'late_paid_terminal_state';
    public const RECON_PROVIDER_TX_UNAVAILABLE = 'provider_transaction_unavailable';

    /** Provider-side currency of the snapshot amount (MoMo only settles VND). */
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
     * Get the MoMo orderId merchant reference sent to the create call.
     *
     * @return string|null
     */
    public function getOrderRef(): ?string;

    /**
     * Set the MoMo orderId merchant reference.
     *
     * @param string|null $orderRef
     * @return void
     */
    public function setOrderRef(?string $orderRef): void;

    /**
     * Get the MoMo requestId identity of the create call.
     *
     * @return string|null
     */
    public function getRequestId(): ?string;

    /**
     * Set the MoMo requestId identity.
     *
     * @param string|null $requestId
     * @return void
     */
    public function setRequestId(?string $requestId): void;

    /**
     * Get the MoMo transId of the confirmed transaction.
     *
     * @return string|null
     */
    public function getProviderTransactionId(): ?string;

    /**
     * Set the MoMo transId.
     *
     * @param string|null $providerTransactionId
     * @return void
     */
    public function setProviderTransactionId(?string $providerTransactionId): void;

    /**
     * Get the wallet pay URL stored at activation.
     *
     * @return string|null
     */
    public function getPayUrl(): ?string;

    /**
     * Set the wallet pay URL.
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
     * Raw status setter (persistence only — prefer the mark*() transitions).
     *
     * @param string $paymentStatus
     * @return void
     */
    public function setPaymentStatus(string $paymentStatus): void;

    /**
     * Get the snapshot amount in VND (locked at initiation).
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
     * Get the quote payment-contract fingerprint (sha-256) locked at
     * initiation. The order may only be auto-created while the CURRENT
     * quote still matches it.
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
     * Get how much proactive v2/query recovery budget this attempt has consumed.
     *
     * @return int
     */
    public function getRecoveryAttempts(): int;

    /**
     * Set the recovery budget consumed.
     *
     * @param int $recoveryAttempts
     * @return void
     */
    public function setRecoveryAttempts(int $recoveryAttempts): void;

    /**
     * Whether proactive recovery queries are exhausted for this attempt
     * (operational marker only — an authenticated IPN/Return arriving later
     * still resolves the payment).
     *
     * @return bool
     */
    public function isRecoveryExhausted(): bool;

    /**
     * Set the recovery-exhausted operational marker.
     *
     * @param bool $recoveryExhausted
     * @return void
     */
    public function setRecoveryExhausted(bool $recoveryExhausted): void;

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
     * Get the email dispatch claim token (unix ts), null when none.
     *
     * @return int|null
     */
    public function getEmailDispatch(): ?int;

    /**
     * Set the email dispatch claim token.
     *
     * @param int|null $emailDispatch
     * @return void
     */
    public function setEmailDispatch(?int $emailDispatch): void;

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
     * Whether this attempt may be reused for a fresh Start redirect
     * (ACTIVE with a stored pay URL and TTL not elapsed).
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
     * INITIATED -> ACTIVE (provider transaction created, pay URL stored).
     *
     * @param string $payUrl
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException On illegal transition.
     */
    public function markActive(string $payUrl): static;

    /**
     * INITIATED|ACTIVE -> FAILED (provider declined or errored).
     *
     * @param string $errorMessage
     * @param string|null $providerStatus
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException On illegal transition.
     */
    public function markFailed(string $errorMessage, ?string $providerStatus = 'failed'): static;

    /**
     * INITIATED|ACTIVE -> STALE (superseded by a newer attempt).
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException On illegal transition.
     */
    public function markStale(): static;

    /**
     * ACTIVE -> PAID (provider confirmed payment, order unbound).
     *
     * A null/empty provider id never overwrites a recorded one.
     *
     * @param string|null $providerTransactionId
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException On illegal transition.
     */
    public function markPaid(?string $providerTransactionId = null): static;

    /**
     * ACTIVE|PAID -> FINALIZED (order placed and bound, terminal).
     *
     * @param int $orderId
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException On illegal transition.
     */
    public function markFinalized(int $orderId): static;
}
