<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Api\Data;

interface RefundInterface
{
    /**
     * String constants for property names
     */
    public const ENTITY_ID = "entity_id";
    public const ORDER_ID = "order_id";
    public const INCREMENT_ID = "increment_id";
    public const ADDITIONAL_INFORMATION = "additional_information";
    public const M_REFUND_ID = "m_refund_id";
    public const IS_PROCESSED = "is_processed";
    public const AMOUNT = "amount";
    public const CREDIT_MEMO_ID = "credit_memo_id";
    public const QUERY_ATTEMPTS = "query_attempts";
    public const LAST_ERROR = "last_error";
    public const REFUND_STATE = "refund_state";
    public const ACTIVE_CLAIM = "active_claim";

    /**
     * Semantic refund outcome states (TASK-CG6BM7 corrective round 2/3).
     * query_attempts saturation alone must NEVER imply "safe to refund
     * again": blocking a new refund request is decided by refund_state.
     *
     * State machine v3 (round 3):
     *  initiating -> processing | unknown | confirmed_fail
     *  processing -> confirmed_success | confirmed_fail | unknown (quarantine)
     *  unknown    -> resolved only deliberately (KEEPS BLOCKING)
     *  provider_success_local_pending -> confirmed_success (finalize only)
     *
     * Blocking set = initiating + processing + unknown +
     * provider_success_local_pending; released by confirmed_success /
     * confirmed_fail.
     */
    public const REFUND_STATE_INITIATING = "initiating";
    public const REFUND_STATE_PROCESSING = "processing";
    public const REFUND_STATE_UNKNOWN = "unknown";
    public const REFUND_STATE_PROVIDER_SUCCESS_LOCAL_PENDING = "provider_success_local_pending";
    public const REFUND_STATE_CONFIRMED_SUCCESS = "confirmed_success";
    public const REFUND_STATE_CONFIRMED_FAIL = "confirmed_fail";

    /**
     * Evidence prefix for provider-confirmed refusal (also used by the
     * state backfill data patch to classify resolved FAIL history).
     */
    public const STATE_EVIDENCE_REFUND_FAILED = 'refund_failed:';
    public const PROCESSED = true;
    public const NOT_PROCESSED = false;

    /**
     * Getter for EntityId.
     *
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * Setter for EntityId.
     *
     * @param int|null $entityId
     *
     * @return void
     */
    public function setEntityId(?int $entityId): void;

    /**
     * Getter for OrderId.
     *
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * Setter for OrderId.
     *
     * @param int|null $orderId
     *
     * @return void
     */
    public function setOrderId(?int $orderId): void;

    /**
     * Getter for CreditMemoId.
     *
     * @return int|null
     */
    public function getCreditMemoId(): ?int;

    /**
     * Setter for CreditMemoId.
     *
     * @param int|null $creditMemoId
     *
     * @return void
     */
    public function setCreditMemoId(?int $creditMemoId): void;

    /**
     * Getter for IncrementId.
     *
     * @return string|null
     */
    public function getIncrementId(): ?string;

    /**
     * Setter for IncrementId.
     *
     * @param string|null $incrementId
     *
     * @return void
     */
    public function setIncrementId(?string $incrementId): void;

    /**
     * Getter for AdditionalInformation.
     *
     * @return string|null
     */
    public function getAdditionalInformation(): ?string;

    /**
     * Setter for AdditionalInformation.
     *
     * @param string|null $additionalInformation
     *
     * @return void
     */
    public function setAdditionalInformation(?string $additionalInformation): void;

    /**
     * Getter for MRefundId.
     *
     * @return string|null
     */
    public function getMRefundId(): ?string;

    /**
     * Setter for MRefundId.
     *
     * @param string|null $mRefundId
     *
     * @return void
     */
    public function setMRefundId(?string $mRefundId): void;

    /**
     * Getter for IsProcessed.
     *
     * @return bool|null
     */
    public function getIsProcessed(): ?bool;

    /**
     * Setter for IsProcessed.
     *
     * @param bool|null $isProcessed
     *
     * @return void
     */
    public function setIsProcessed(?bool $isProcessed): void;

    /**
     * @param float $amount
     * @return void
     */
    public function setAmount(float $amount): void;

    /**
     * @return float|null
     */
    public function getAmount(): ?float;

    /**
     * Getter for QueryAttempts.
     *
     * @return int
     */
    public function getQueryAttempts(): int;

    /**
     * Setter for QueryAttempts.
     *
     * @param int $queryAttempts
     *
     * @return void
     */
    public function setQueryAttempts(int $queryAttempts): void;

    /**
     * Getter for LastError.
     *
     * @return string|null
     */
    public function getLastError(): ?string;

    /**
     * Setter for LastError.
     *
     * @param string|null $lastError
     *
     * @return void
     */
    public function setLastError(?string $lastError): void;

    /**
     * Getter for RefundState.
     *
     * @return string|null
     */
    public function getRefundState(): ?string;

    /**
     * Setter for RefundState.
     *
     * @param string|null $refundState
     *
     * @return void
     */
    public function setRefundState(?string $refundState): void;

    /**
     * Getter for ActiveClaim (1 = THE atomic active attempt for the order).
     *
     * @return int|null
     */
    public function getActiveClaim(): ?int;

    /**
     * Setter for ActiveClaim.
     *
     * @param int|null $activeClaim
     *
     * @return void
     */
    public function setActiveClaim(?int $activeClaim): void;
}
