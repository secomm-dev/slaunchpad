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
}
