<?php
/**
 * Data contract for a MoMo creditmemo refund request row.
 *
 * One row per logical refund operation against a captured MoMo payment.
 * The row is the idempotency anchor: its minted refund_order_id/request_id
 * identify the operation at the provider, and its open/terminal state is
 * the duplicate-submission guard.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Api\Data;

/**
 * MoMo refund request row (MOMO-02).
 *
 * Lifecycle: pending → success | failed | unknown; unknown → success |
 * failed (operator resolve only). Terminal states have no outgoing edges.
 *
 * @api
 */
interface RefundRequestInterface
{
    /**#@+
     * Field names
     */
    public const ENTITY_ID = 'entity_id';
    public const ORDER_ID = 'order_id';
    public const ORDER_INCREMENT_ID = 'order_increment_id';
    public const INVOICE_ID = 'invoice_id';
    public const CREDITMEMO_ID = 'creditmemo_id';
    public const MOMO_ORDER_REF = 'momo_order_ref';
    public const MOMO_TRANS_ID = 'momo_trans_id';
    public const REFUND_ORDER_ID = 'refund_order_id';
    public const REQUEST_ID = 'request_id';
    public const PROVIDER_TRANSACTION_ID = 'provider_transaction_id';
    public const AMOUNT = 'amount';
    public const CURRENCY = 'currency';
    public const STATUS = 'status';
    public const RESPONSE_CODE = 'response_code';
    public const RESPONSE_MESSAGE = 'response_message';
    public const CLASSIFICATION_REASON = 'classification_reason';
    public const LAST_ERROR = 'last_error';
    public const OPEN_FLAG = 'open_flag';
    public const STORE_ID = 'store_id';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
    public const RESOLVED_AT = 'resolved_at';
    /**#@-*/

    /**#@+
     * Lifecycle statuses
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_UNKNOWN = 'unknown';
    /**#@-*/

    /**#@+
     * Machine-readable classification reasons
     */
    public const REASON_PROVIDER_CONFIRMED = 'provider_confirmed';
    public const REASON_PROVIDER_REFUSED = 'provider_refused';
    public const REASON_PROVIDER_PROCESSING = 'provider_processing';
    public const REASON_ECHO_MISMATCH = 'echo_mismatch';
    public const REASON_MALFORMED_RESPONSE = 'malformed_response';
    public const REASON_TRANSPORT_ERROR = 'transport_error';
    public const REASON_STALE_PENDING_SWEEP = 'stale_pending_sweep';
    public const REASON_QUERY_REJECTED = 'query_rejected';
    /**#@-*/

    /**
     * Legal lifecycle transitions (from => [to...]).
     */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_SUCCESS, self::STATUS_FAILED, self::STATUS_UNKNOWN],
        self::STATUS_UNKNOWN => [self::STATUS_SUCCESS, self::STATUS_FAILED],
        self::STATUS_SUCCESS => [],
        self::STATUS_FAILED => [],
    ];

    /**
     * @return int|null
     */
    public function getEntityId(): ?int;

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
     * @return string
     */
    public function getOrderIncrementId(): string;

    /**
     * @param string $orderIncrementId
     * @return void
     */
    public function setOrderIncrementId(string $orderIncrementId): void;

    /**
     * @return int|null
     */
    public function getInvoiceId(): ?int;

    /**
     * @param int|null $invoiceId
     * @return void
     */
    public function setInvoiceId(?int $invoiceId): void;

    /**
     * @return int|null
     */
    public function getCreditmemoId(): ?int;

    /**
     * @param int|null $creditmemoId
     * @return void
     */
    public function setCreditmemoId(?int $creditmemoId): void;

    /**
     * MoMo orderId of the ORIGINAL captured transaction.
     *
     * @return string
     */
    public function getMomoOrderRef(): string;

    /**
     * @param string $momoOrderRef
     * @return void
     */
    public function setMomoOrderRef(string $momoOrderRef): void;

    /**
     * MoMo transId of the ORIGINAL captured transaction.
     *
     * @return string
     */
    public function getMomoTransId(): string;

    /**
     * @param string $momoTransId
     * @return void
     */
    public function setMomoTransId(string $momoTransId): void;

    /**
     * MoMo orderId minted for the refund (differs from the purchase orderId).
     *
     * @return string
     */
    public function getRefundOrderId(): string;

    /**
     * @param string $refundOrderId
     * @return void
     */
    public function setRefundOrderId(string $refundOrderId): void;

    /**
     * MoMo requestId minted for the refund (provider idempotency key).
     *
     * @return string
     */
    public function getRequestId(): string;

    /**
     * @param string $requestId
     * @return void
     */
    public function setRequestId(string $requestId): void;

    /**
     * MoMo transId of the refund itself (set on SUCCESS).
     *
     * @return string|null
     */
    public function getProviderTransactionId(): ?string;

    /**
     * @param string|null $providerTransactionId
     * @return void
     */
    public function setProviderTransactionId(?string $providerTransactionId): void;

    /**
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
     * @return string
     */
    public function getStatus(): string;

    /**
     * @param string $status
     * @return void
     */
    public function setStatus(string $status): void;

    /**
     * @return string|null
     */
    public function getResponseCode(): ?string;

    /**
     * @param string|null $responseCode
     * @return void
     */
    public function setResponseCode(?string $responseCode): void;

    /**
     * @return string|null
     */
    public function getResponseMessage(): ?string;

    /**
     * @param string|null $responseMessage
     * @return void
     */
    public function setResponseMessage(?string $responseMessage): void;

    /**
     * @return string|null
     */
    public function getClassificationReason(): ?string;

    /**
     * @param string|null $classificationReason
     * @return void
     */
    public function setClassificationReason(?string $classificationReason): void;

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
     * Whether the row is open (pending/unknown) — 1 for open, NULL terminal.
     *
     * @return bool
     */
    public function isOpen(): bool;

    /**
     * @param bool $open
     * @return void
     */
    public function setOpenFlag(bool $open): void;

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
     * @return string|null
     */
    public function getResolvedAt(): ?string;

    /**
     * Whether the current status may transition to $status.
     *
     * @param string $status
     * @return bool
     */
    public function canTransitionTo(string $status): bool;

    /**
     * Whether the row has reached a terminal state (success/failed).
     *
     * @return bool
     */
    public function isTerminal(): bool;
}
