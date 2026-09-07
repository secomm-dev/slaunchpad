<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Model\ResourceModel\PaymentAttemptResource;

/**
 * @method PaymentAttemptResource getResource()
 */
class PaymentAttempt extends AbstractModel implements PaymentAttemptInterface
{
    /**
     * Allowed lifecycle transitions. Every mark*() method routes through this
     * map — an illegal transition is a programming/data error and throws
     * instead of silently overwriting state.
     */
    private const TRANSITIONS = [
        self::STATUS_INITIATED => [
            self::STATUS_ACTIVE,
            self::STATUS_FAILED,
            self::STATUS_STALE,
        ],
        self::STATUS_ACTIVE => [
            self::STATUS_PAID,
            self::STATUS_FAILED,
            self::STATUS_STALE,
            self::STATUS_EXPIRED,
        ],
        self::STATUS_PAID => [
            self::STATUS_FINALIZED,
        ],
        self::STATUS_FINALIZED => [],
        self::STATUS_FAILED => [],
        self::STATUS_STALE => [],
        self::STATUS_EXPIRED => [],
    ];

    /**
     * @var string
     */
    protected $_eventPrefix = 'secomm_zalopay_payment_attempt_model';

    /**
     * Initialize magento model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(PaymentAttemptResource::class);
    }

    /**
     * @inheritDoc
     */
    public function getEntityId(): ?int
    {
        $entityId = $this->getData(self::ENTITY_ID);
        return $entityId === null ? null : (int)$entityId;
    }

    /**
     * Signature-compatible with AbstractModel::setEntityId (untyped param).
     *
     * @param int|null $entityId
     * @return $this
     */
    public function setEntityId($entityId): static
    {
        $this->setData(self::ENTITY_ID, $entityId);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getQuoteId(): int
    {
        return (int)$this->getData(self::QUOTE_ID);
    }

    /**
     * @inheritDoc
     */
    public function setQuoteId(int $quoteId): void
    {
        $this->setData(self::QUOTE_ID, $quoteId);
    }

    /**
     * @inheritDoc
     */
    public function getReservedOrderId(): string
    {
        return (string)$this->getData(self::RESERVED_ORDER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setReservedOrderId(string $reservedOrderId): void
    {
        $this->setData(self::RESERVED_ORDER_ID, $reservedOrderId);
    }

    /**
     * @inheritDoc
     */
    public function getAppTransId(): ?string
    {
        $value = $this->getData(self::APP_TRANS_ID);
        return $value === null || $value === '' ? null : (string)$value;
    }

    /**
     * @inheritDoc
     */
    public function setAppTransId(?string $appTransId): void
    {
        $this->setData(self::APP_TRANS_ID, $appTransId);
    }

    /**
     * @inheritDoc
     */
    public function getProviderTransactionId(): ?string
    {
        $value = $this->getData(self::PROVIDER_TRANSACTION_ID);
        return $value === null || $value === '' ? null : (string)$value;
    }

    /**
     * @inheritDoc
     */
    public function setProviderTransactionId(?string $providerTransactionId): void
    {
        $this->setData(self::PROVIDER_TRANSACTION_ID, $providerTransactionId);
    }

    /**
     * @inheritDoc
     */
    public function getPayUrl(): ?string
    {
        $value = $this->getData(self::PAY_URL);
        return $value === null || $value === '' ? null : (string)$value;
    }

    /**
     * @inheritDoc
     */
    public function setPayUrl(?string $payUrl): void
    {
        $this->setData(self::PAY_URL, $payUrl);
    }

    /**
     * @inheritDoc
     */
    public function getProviderStatus(): ?string
    {
        $value = $this->getData(self::PROVIDER_STATUS);
        return $value === null || $value === '' ? null : (string)$value;
    }

    /**
     * @inheritDoc
     */
    public function setProviderStatus(?string $providerStatus): void
    {
        $this->setData(self::PROVIDER_STATUS, $providerStatus);
    }

    /**
     * @inheritDoc
     */
    public function getPaymentStatus(): string
    {
        return (string)$this->getData(self::PAYMENT_STATUS);
    }

    /**
     * @inheritDoc
     */
    public function setPaymentStatus(string $paymentStatus): void
    {
        $this->setData(self::PAYMENT_STATUS, $paymentStatus);
    }

    /**
     * @inheritDoc
     */
    public function getAmount(): int
    {
        return (int)$this->getData(self::AMOUNT);
    }

    /**
     * @inheritDoc
     */
    public function setAmount(int $amount): void
    {
        $this->setData(self::AMOUNT, $amount);
    }

    /**
     * @inheritDoc
     */
    public function getCurrency(): string
    {
        return (string)$this->getData(self::CURRENCY);
    }

    /**
     * @inheritDoc
     */
    public function setCurrency(string $currency): void
    {
        $this->setData(self::CURRENCY, $currency);
    }

    /**
     * @inheritDoc
     */
    public function getContractHash(): ?string
    {
        $value = $this->getData(self::CONTRACT_HASH);
        return $value === null || $value === '' ? null : (string)$value;
    }

    /**
     * @inheritDoc
     */
    public function setContractHash(?string $contractHash): void
    {
        $this->setData(self::CONTRACT_HASH, $contractHash);
    }

    /**
     * @inheritDoc
     */
    public function getOrderId(): ?int
    {
        $orderId = $this->getData(self::ORDER_ID);
        return $orderId === null || $orderId === '' ? null : (int)$orderId;
    }

    /**
     * @inheritDoc
     */
    public function setOrderId(?int $orderId): void
    {
        $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * @inheritDoc
     */
    public function getLastError(): ?string
    {
        $value = $this->getData(self::LAST_ERROR);
        return $value === null || $value === '' ? null : (string)$value;
    }

    /**
     * @inheritDoc
     */
    public function setLastError(?string $lastError): void
    {
        $this->setData(self::LAST_ERROR, $lastError);
    }

    /**
     * @inheritDoc
     */
    public function getRetryCount(): int
    {
        return (int)$this->getData(self::RETRY_COUNT);
    }

    /**
     * @inheritDoc
     */
    public function setRetryCount(int $retryCount): void
    {
        $this->setData(self::RETRY_COUNT, $retryCount);
    }

    /**
     * @inheritDoc
     */
    public function getStoreId(): int
    {
        return (int)$this->getData(self::STORE_ID);
    }

    /**
     * @inheritDoc
     */
    public function setStoreId(int $storeId): void
    {
        $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(?string $createdAt): void
    {
        $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setUpdatedAt(?string $updatedAt): void
    {
        $this->setData(self::UPDATED_AT, $updatedAt);
    }

    /**
     * @inheritDoc
     */
    public function getExpiresAt(): ?string
    {
        $value = $this->getData(self::EXPIRES_AT);
        return $value === null || $value === '' ? null : (string)$value;
    }

    /**
     * @inheritDoc
     */
    public function setExpiresAt(?string $expiresAt): void
    {
        $this->setData(self::EXPIRES_AT, $expiresAt);
    }

    /**
     * Whether the attempt may still move to the given status.
     *
     * @param string $status
     * @return bool
     */
    public function canTransitionTo(string $status): bool
    {
        $current = $this->getPaymentStatus();
        return in_array($status, self::TRANSITIONS[$current] ?? [], true);
    }

    /**
     * Whether the attempt is in a terminal lifecycle state.
     *
     * @return bool
     */
    public function isTerminal(): bool
    {
        return self::TRANSITIONS[$this->getPaymentStatus()] === [];
    }

    /**
     * Whether this attempt may be reused as-is for a fresh Start redirect:
     * provider transaction created (pay URL stored) and TTL not elapsed.
     * A SUCCESS/PAID transaction is never reused as a new attempt.
     *
     * @param string|null $now
     * @return bool
     */
    public function isReusable(?string $now = null): bool
    {
        if ($this->getPaymentStatus() !== self::STATUS_ACTIVE || !$this->getPayUrl()) {
            return false;
        }
        return !$this->isExpired($now);
    }

    /**
     * Whether the attempt TTL has elapsed.
     *
     * @param string|null $now
     * @return bool
     */
    public function isExpired(?string $now = null): bool
    {
        $expiresAt = $this->getExpiresAt();
        if ($expiresAt === null) {
            return false;
        }
        $now ??= date('Y-m-d H:i:s');
        return strtotime($expiresAt) <= strtotime($now);
    }

    /**
     * INITIATED -> ACTIVE: provider transaction created, pay URL stored.
     *
     * @param string $payUrl
     * @return $this
     * @throws LocalizedException
     */
    public function markActive(string $payUrl): static
    {
        $this->transition(self::STATUS_ACTIVE);
        $this->setPayUrl($payUrl);
        $this->setProviderStatus('created');
        return $this;
    }

    /**
     * INITIATED|ACTIVE -> FAILED: provider declined or errored.
     *
     * @param string $errorMessage
     * @param string|null $providerStatus
     * @return $this
     * @throws LocalizedException
     */
    public function markFailed(string $errorMessage, ?string $providerStatus = 'failed'): static
    {
        $this->transition(self::STATUS_FAILED);
        $this->setLastError($errorMessage);
        if ($providerStatus !== null) {
            $this->setProviderStatus($providerStatus);
        }
        return $this;
    }

    /**
     * INITIATED|ACTIVE -> STALE: superseded (quote total changed / new attempt).
     *
     * @return $this
     * @throws LocalizedException
     */
    public function markStale(): static
    {
        $this->transition(self::STATUS_STALE);
        return $this;
    }

    /**
     * ACTIVE -> PAID: provider confirmed payment; Magento order still unbound.
     *
     * @param string|null $providerTransactionId
     * @return $this
     * @throws LocalizedException
     */
    public function markPaid(?string $providerTransactionId = null): static
    {
        $this->transition(self::STATUS_PAID);
        if ($providerTransactionId !== null && $providerTransactionId !== '') {
            $this->setProviderTransactionId($providerTransactionId);
        }
        $this->setProviderStatus('paid');
        return $this;
    }

    /**
     * ACTIVE|PAID -> FINALIZED: Magento order placed and bound (terminal).
     *
     * @param int $orderId
     * @return $this
     * @throws LocalizedException
     */
    public function markFinalized(int $orderId): static
    {
        $this->transition(self::STATUS_FINALIZED);
        $this->setOrderId($orderId);
        return $this;
    }

    /**
     * Guarded transition.
     *
     * @param string $status
     * @return void
     * @throws LocalizedException
     */
    private function transition(string $status): void
    {
        if (!$this->canTransitionTo($status)) {
            throw new LocalizedException(
                __(
                    'Illegal ZaloPay payment attempt transition "%1" -> "%2" (attempt #%3).',
                    $this->getPaymentStatus() ?: 'new',
                    $status,
                    $this->getEntityId() ?? 0
                )
            );
        }
        $this->setPaymentStatus($status);
    }
}
