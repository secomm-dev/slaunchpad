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
    public const ORDER_ID = 'order_id';
    public const LAST_ERROR = 'last_error';
    public const RETRY_COUNT = 'retry_count';
    public const STORE_ID = 'store_id';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
    public const EXPIRES_AT = 'expires_at';

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
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * @return int
     */
    public function getQuoteId(): int;

    /**
     * @param int $quoteId
     * @return void
     */
    public function setQuoteId(int $quoteId): void;

    /**
     * @return string
     */
    public function getReservedOrderId(): string;

    /**
     * @param string $reservedOrderId
     * @return void
     */
    public function setReservedOrderId(string $reservedOrderId): void;

    /**
     * @return string|null
     */
    public function getAppTransId(): ?string;

    /**
     * @param string|null $appTransId
     * @return void
     */
    public function setAppTransId(?string $appTransId): void;

    /**
     * @return string|null
     */
    public function getProviderTransactionId(): ?string;

    /**
     * @param string|null $providerTransactionId
     * @return void
     */
    public function setProviderTransactionId(?string $providerTransactionId): void;

    /**
     * @return string|null
     */
    public function getPayUrl(): ?string;

    /**
     * @param string|null $payUrl
     * @return void
     */
    public function setPayUrl(?string $payUrl): void;

    /**
     * @return string|null
     */
    public function getProviderStatus(): ?string;

    /**
     * @param string|null $providerStatus
     * @return void
     */
    public function setProviderStatus(?string $providerStatus): void;

    /**
     * @return string
     */
    public function getPaymentStatus(): string;

    /**
     * Raw status setter. Prefer the explicit mark*() transitions on
     * \Secomm\ZaloPay\Model\PaymentAttempt — this exists for persistence only.
     *
     * @param string $paymentStatus
     * @return void
     */
    public function setPaymentStatus(string $paymentStatus): void;

    /**
     * Snapshot amount in VND, locked at initiation.
     *
     * @return int
     */
    public function getAmount(): int;

    /**
     * @param int $amount
     * @return void
     */
    public function setAmount(int $amount): void;

    /**
     * @return string
     */
    public function getCurrency(): string;

    /**
     * @param string $currency
     * @return void
     */
    public function setCurrency(string $currency): void;

    /**
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * @param int|null $orderId
     * @return void
     */
    public function setOrderId(?int $orderId): void;

    /**
     * @return string|null
     */
    public function getLastError(): ?string;

    /**
     * @param string|null $lastError
     * @return void
     */
    public function setLastError(?string $lastError): void;

    /**
     * @return int
     */
    public function getRetryCount(): int;

    /**
     * @param int $retryCount
     * @return void
     */
    public function setRetryCount(int $retryCount): void;

    /**
     * @return int
     */
    public function getStoreId(): int;

    /**
     * @param int $storeId
     * @return void
     */
    public function setStoreId(int $storeId): void;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @param string|null $createdAt
     * @return void
     */
    public function setCreatedAt(?string $createdAt): void;

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * @param string|null $updatedAt
     * @return void
     */
    public function setUpdatedAt(?string $updatedAt): void;

    /**
     * @return string|null
     */
    public function getExpiresAt(): ?string;

    /**
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
     * Whether this attempt may be reused for a fresh Start redirect:
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
     * INITIATED -> ACTIVE: provider transaction created, pay URL stored.
     *
     * @param string $payUrl
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException On illegal transition.
     */
    public function markActive(string $payUrl): static;

    /**
     * INITIATED|ACTIVE -> FAILED: provider declined or errored.
     *
     * @param string $errorMessage
     * @param string|null $providerStatus
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException On illegal transition.
     */
    public function markFailed(string $errorMessage, ?string $providerStatus = 'failed'): static;

    /**
     * INITIATED|ACTIVE -> STALE: superseded (quote total changed / new attempt).
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException On illegal transition.
     */
    public function markStale(): static;

    /**
     * ACTIVE -> PAID: provider confirmed payment; Magento order still unbound.
     *
     * @param string|null $providerTransactionId
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException On illegal transition.
     */
    public function markPaid(?string $providerTransactionId = null): static;

    /**
     * ACTIVE|PAID -> FINALIZED: Magento order placed and bound (terminal).
     *
     * @param int $orderId
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException On illegal transition.
     */
    public function markFinalized(int $orderId): static;
}
