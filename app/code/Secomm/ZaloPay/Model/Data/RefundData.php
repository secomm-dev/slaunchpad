<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Model\Data;

use Secomm\ZaloPay\Api\Data\RefundInterface;
use Magento\Framework\DataObject;

class RefundData extends DataObject implements RefundInterface
{
    /**
     * Getter for EntityId.
     *
     * @return int|null
     */
    public function getEntityId(): ?int
    {
        return $this->getData(self::ENTITY_ID) === null ? null
            : (int)$this->getData(self::ENTITY_ID);
    }

    /**
     * Setter for EntityId.
     *
     * @param int|null $entityId
     *
     * @return void
     */
    public function setEntityId(?int $entityId): void
    {
        $this->setData(self::ENTITY_ID, $entityId);
    }

    /**
     * Getter for OrderId.
     *
     * @return int|null
     */
    public function getOrderId(): ?int
    {
        return $this->getData(self::ORDER_ID) === null ? null
            : (int)$this->getData(self::ORDER_ID);
    }

    /**
     * Setter for OrderId.
     *
     * @param int|null $orderId
     *
     * @return void
     */
    public function setOrderId(?int $orderId): void
    {
        $this->setData(self::ORDER_ID, $orderId);
    }

   /**
    * Getter for CreditMemoId.
    *
    * @return int|null
    */
    public function getCreditMemoId(): ?int
    {
        return $this->getData(self::CREDIT_MEMO_ID) === null ? null
            : (int)$this->getData(self::CREDIT_MEMO_ID);
    }

    /**
     * Setter for CreditMemoId.
     *
     * @param int|null $creditMemoId
     *
     * @return void
     */
    public function setCreditMemoId(?int $creditMemoId): void
    {
        $this->setData(self::CREDIT_MEMO_ID, $creditMemoId);
    }

    /**
     * Getter for IncrementId.
     *
     * @return string|null
     */
    public function getIncrementId(): ?string
    {
        return $this->getData(self::INCREMENT_ID);
    }

    /**
     * Setter for IncrementId.
     *
     * @param string|null $incrementId
     *
     * @return void
     */
    public function setIncrementId(?string $incrementId): void
    {
        $this->setData(self::INCREMENT_ID, $incrementId);
    }

    /**
     * Getter for AdditionalInformation.
     *
     * @return string|null
     */
    public function getAdditionalInformation(): ?string
    {
        return $this->getData(self::ADDITIONAL_INFORMATION);
    }

    /**
     * Setter for AdditionalInformation.
     *
     * @param string|null $additionalInformation
     *
     * @return void
     */
    public function setAdditionalInformation(?string $additionalInformation): void
    {
        $this->setData(self::ADDITIONAL_INFORMATION, $additionalInformation);
    }

    /**
     * Getter for MRefundId.
     *
     * @return string|null
     */
    public function getMRefundId(): ?string
    {
        return $this->getData(self::M_REFUND_ID);
    }

    /**
     * Setter for MRefundId.
     *
     * @param string|null $mRefundId
     *
     * @return void
     */
    public function setMRefundId(?string $mRefundId): void
    {
        $this->setData(self::M_REFUND_ID, $mRefundId);
    }

    /**
     * Getter for IsProcessed.
     *
     * @return bool|null
     */
    public function getIsProcessed(): ?bool
    {
        return $this->getData(self::IS_PROCESSED) === null ? null
            : (bool)$this->getData(self::IS_PROCESSED);
    }

    /**
     * Setter for IsProcessed.
     *
     * @param bool|null $isProcessed
     *
     * @return void
     */
    public function setIsProcessed(?bool $isProcessed): void
    {
        $this->setData(self::IS_PROCESSED, $isProcessed);
    }

    /**
     * Getter for IsProcessed.
     *
     * @return float|null
     */
    public function getAmount(): ?float
    {
        return $this->getData(self::AMOUNT) === null ? null
            : (float)$this->getData(self::AMOUNT);
    }

    /**
     * Setter for IsProcessed.
     *
     * @param float|null $amount
     *
     * @return void
     */
    public function setAmount(?float $amount): void
    {
        $this->setData(self::AMOUNT, $amount);
    }

    /**
     * Getter for QueryAttempts.
     *
     * @return int
     */
    public function getQueryAttempts(): int
    {
        return (int)$this->getData(self::QUERY_ATTEMPTS);
    }

    /**
     * Setter for QueryAttempts.
     *
     * @param int $queryAttempts
     *
     * @return void
     */
    public function setQueryAttempts(int $queryAttempts): void
    {
        $this->setData(self::QUERY_ATTEMPTS, $queryAttempts);
    }

    /**
     * Getter for LastError.
     *
     * @return string|null
     */
    public function getLastError(): ?string
    {
        $v = $this->getData(self::LAST_ERROR);

        return $v === null ? null : (string)$v;
    }

    /**
     * Setter for LastError.
     *
     * @param string|null $lastError
     *
     * @return void
     */
    public function setLastError(?string $lastError): void
    {
        $this->setData(self::LAST_ERROR, $lastError);
    }

    /**
     * Getter for RefundState.
     *
     * @return string|null
     */
    public function getRefundState(): ?string
    {
        $v = $this->getData(self::REFUND_STATE);

        return $v === null ? null : (string)$v;
    }

    /**
     * Setter for RefundState.
     *
     * @param string|null $refundState
     *
     * @return void
     */
    public function setRefundState(?string $refundState): void
    {
        $this->setData(self::REFUND_STATE, $refundState);
    }

    /**
     * Getter for ActiveClaim (1 = THE atomic active attempt for the order).
     *
     * @return int|null
     */
    public function getActiveClaim(): ?int
    {
        $v = $this->getData(self::ACTIVE_CLAIM);

        return $v === null ? null : (int)$v;
    }

    /**
     * Setter for ActiveClaim.
     *
     * @param int|null $activeClaim
     *
     * @return void
     */
    public function setActiveClaim(?int $activeClaim): void
    {
        $this->setData(self::ACTIVE_CLAIM, $activeClaim);
    }
}
