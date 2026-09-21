<?php
/**
 * MoMo refund request entity (plain DataObject-backed).
 *
 * The row lives outside the sales transaction (independent connection), so
 * the entity is intentionally NOT an AbstractModel bound to a standard
 * resource model: the standard resource path resolves to the same MySQL
 * connection as the CreditmemoService transaction and any write inside it
 * would roll back with the creditmemo.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Model;

use Magento\Framework\DataObject;
use Secomm\MoMo\Api\Data\RefundRequestInterface;

class RefundRequest extends DataObject implements RefundRequestInterface
{
    /**
     * @inheritdoc
     */
    public function getEntityId(): ?int
    {
        $id = $this->getData(self::ENTITY_ID);

        return $id === null ? null : (int)$id;
    }

    /**
     * @inheritdoc
     */
    public function getOrderId(): ?int
    {
        $id = $this->getData(self::ORDER_ID);

        return $id === null ? null : (int)$id;
    }

    /**
     * @inheritdoc
     */
    public function setOrderId(?int $orderId): void
    {
        $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * @inheritdoc
     */
    public function getOrderIncrementId(): string
    {
        return (string)$this->getData(self::ORDER_INCREMENT_ID);
    }

    /**
     * @inheritdoc
     */
    public function setOrderIncrementId(string $orderIncrementId): void
    {
        $this->setData(self::ORDER_INCREMENT_ID, $orderIncrementId);
    }

    /**
     * @inheritdoc
     */
    public function getInvoiceId(): ?int
    {
        $id = $this->getData(self::INVOICE_ID);

        return $id === null ? null : (int)$id;
    }

    /**
     * @inheritdoc
     */
    public function setInvoiceId(?int $invoiceId): void
    {
        $this->setData(self::INVOICE_ID, $invoiceId);
    }

    /**
     * @inheritdoc
     */
    public function getCreditmemoId(): ?int
    {
        $id = $this->getData(self::CREDITMEMO_ID);

        return $id === null ? null : (int)$id;
    }

    /**
     * @inheritdoc
     */
    public function setCreditmemoId(?int $creditmemoId): void
    {
        $this->setData(self::CREDITMEMO_ID, $creditmemoId);
    }

    /**
     * @inheritdoc
     */
    public function getMomoOrderRef(): string
    {
        return (string)$this->getData(self::MOMO_ORDER_REF);
    }

    /**
     * @inheritdoc
     */
    public function setMomoOrderRef(string $momoOrderRef): void
    {
        $this->setData(self::MOMO_ORDER_REF, $momoOrderRef);
    }

    /**
     * @inheritdoc
     */
    public function getMomoTransId(): string
    {
        return (string)$this->getData(self::MOMO_TRANS_ID);
    }

    /**
     * @inheritdoc
     */
    public function setMomoTransId(string $momoTransId): void
    {
        $this->setData(self::MOMO_TRANS_ID, $momoTransId);
    }

    /**
     * @inheritdoc
     */
    public function getRefundOrderId(): string
    {
        return (string)$this->getData(self::REFUND_ORDER_ID);
    }

    /**
     * @inheritdoc
     */
    public function setRefundOrderId(string $refundOrderId): void
    {
        $this->setData(self::REFUND_ORDER_ID, $refundOrderId);
    }

    /**
     * @inheritdoc
     */
    public function getRequestId(): string
    {
        return (string)$this->getData(self::REQUEST_ID);
    }

    /**
     * @inheritdoc
     */
    public function setRequestId(string $requestId): void
    {
        $this->setData(self::REQUEST_ID, $requestId);
    }

    /**
     * @inheritdoc
     */
    public function getProviderTransactionId(): ?string
    {
        $id = $this->getData(self::PROVIDER_TRANSACTION_ID);

        return $id === null ? null : (string)$id;
    }

    /**
     * @inheritdoc
     */
    public function setProviderTransactionId(?string $providerTransactionId): void
    {
        $this->setData(self::PROVIDER_TRANSACTION_ID, $providerTransactionId);
    }

    /**
     * @inheritdoc
     */
    public function getAmount(): int
    {
        return (int)$this->getData(self::AMOUNT);
    }

    /**
     * @inheritdoc
     */
    public function setAmount(int $amount): void
    {
        $this->setData(self::AMOUNT, $amount);
    }

    /**
     * @inheritdoc
     */
    public function getCurrency(): string
    {
        return (string)($this->getData(self::CURRENCY) ?: 'VND');
    }

    /**
     * @inheritdoc
     */
    public function setCurrency(string $currency): void
    {
        $this->setData(self::CURRENCY, $currency);
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): string
    {
        return (string)($this->getData(self::STATUS) ?: self::STATUS_PENDING);
    }

    /**
     * @inheritdoc
     */
    public function setStatus(string $status): void
    {
        $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritdoc
     */
    public function getResponseCode(): ?string
    {
        $code = $this->getData(self::RESPONSE_CODE);

        return $code === null ? null : (string)$code;
    }

    /**
     * @inheritdoc
     */
    public function setResponseCode(?string $responseCode): void
    {
        $this->setData(self::RESPONSE_CODE, $responseCode);
    }

    /**
     * @inheritdoc
     */
    public function getResponseMessage(): ?string
    {
        $message = $this->getData(self::RESPONSE_MESSAGE);

        return $message === null ? null : (string)$message;
    }

    /**
     * @inheritdoc
     */
    public function setResponseMessage(?string $responseMessage): void
    {
        $this->setData(self::RESPONSE_MESSAGE, $responseMessage);
    }

    /**
     * @inheritdoc
     */
    public function getClassificationReason(): ?string
    {
        $reason = $this->getData(self::CLASSIFICATION_REASON);

        return $reason === null ? null : (string)$reason;
    }

    /**
     * @inheritdoc
     */
    public function setClassificationReason(?string $classificationReason): void
    {
        $this->setData(self::CLASSIFICATION_REASON, $classificationReason);
    }

    /**
     * @inheritdoc
     */
    public function getLastError(): ?string
    {
        $error = $this->getData(self::LAST_ERROR);

        return $error === null ? null : (string)$error;
    }

    /**
     * @inheritdoc
     */
    public function setLastError(?string $lastError): void
    {
        $this->setData(self::LAST_ERROR, $lastError);
    }

    /**
     * @inheritdoc
     */
    public function isOpen(): bool
    {
        return (bool)$this->getData(self::OPEN_FLAG);
    }

    /**
     * @inheritdoc
     */
    public function setOpenFlag(bool $open): void
    {
        $this->setData(self::OPEN_FLAG, $open ? 1 : null);
    }

    /**
     * @inheritdoc
     */
    public function getStoreId(): int
    {
        return (int)($this->getData(self::STORE_ID) ?: 0);
    }

    /**
     * @inheritdoc
     */
    public function setStoreId(int $storeId): void
    {
        $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt(): ?string
    {
        $at = $this->getData(self::CREATED_AT);

        return $at === null ? null : (string)$at;
    }

    /**
     * @inheritdoc
     */
    public function getResolvedAt(): ?string
    {
        $at = $this->getData(self::RESOLVED_AT);

        return $at === null ? null : (string)$at;
    }

    /**
     * @inheritdoc
     */
    public function canTransitionTo(string $status): bool
    {
        $allowed = self::TRANSITIONS[$this->getStatus()] ?? [];

        return in_array($status, $allowed, true);
    }

    /**
     * @inheritdoc
     */
    public function isTerminal(): bool
    {
        return $this->canTransitionTo('__none__') === false
            && in_array($this->getStatus(), [self::STATUS_SUCCESS, self::STATUS_FAILED], true);
    }
}
