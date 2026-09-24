<?php
/**
 * MoMo payment attempt model (state machine).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Exception\LocalizedException;
use Secomm\MoMo\Api\Data\PaymentAttemptInterface;

/**
 * Payment attempt ORM model with an explicit transition map: mark*()
 * transitions throw on illegal moves so a late PAID claim can never
 * regress FAILED/STALE/EXPIRED/FINALIZED and terminal states have no
 * outgoing edges.
 */
class PaymentAttempt extends AbstractModel implements PaymentAttemptInterface
{
    /**
     * Legal lifecycle transitions (from => [to...]). Terminal states
     * (finalized/failed/stale/expired) have no outgoing edges.
     */
    public const TRANSITIONS = [
        self::STATUS_INITIATED => [self::STATUS_ACTIVE, self::STATUS_FAILED, self::STATUS_STALE, self::STATUS_EXPIRED],
        self::STATUS_ACTIVE => [self::STATUS_PAID, self::STATUS_FAILED, self::STATUS_STALE, self::STATUS_EXPIRED],
        self::STATUS_PAID => [self::STATUS_FINALIZED],
        self::STATUS_FINALIZED => [],
        self::STATUS_FAILED => [],
        self::STATUS_STALE => [],
        self::STATUS_EXPIRED => [],
    ];

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        // Bind the resource lazily (id field matches PaymentAttemptResource)
        // so the model can be constructed without a booted ObjectManager.
        $this->_resourceName = PaymentAttemptResource::class;
        $this->_idFieldName = 'entity_id';
    }

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
    public function getQuoteId(): int
    {
        return (int)$this->getData(self::QUOTE_ID);
    }

    /**
     * @inheritdoc
     */
    public function setQuoteId(int $quoteId): void
    {
        $this->setData(self::QUOTE_ID, $quoteId);
    }

    /**
     * @inheritdoc
     */
    public function getReservedOrderId(): string
    {
        return (string)$this->getData(self::RESERVED_ORDER_ID);
    }

    /**
     * @inheritdoc
     */
    public function setReservedOrderId(string $reservedOrderId): void
    {
        $this->setData(self::RESERVED_ORDER_ID, $reservedOrderId);
    }

    /**
     * @inheritdoc
     */
    public function getOrderRef(): ?string
    {
        $ref = $this->getData(self::ORDER_REF);

        return $ref === null ? null : (string)$ref;
    }

    /**
     * @inheritdoc
     */
    public function setOrderRef(?string $orderRef): void
    {
        $this->setData(self::ORDER_REF, $orderRef);
    }

    /**
     * @inheritdoc
     */
    public function getRequestId(): ?string
    {
        $id = $this->getData(self::REQUEST_ID);

        return $id === null ? null : (string)$id;
    }

    /**
     * @inheritdoc
     */
    public function setRequestId(?string $requestId): void
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
    public function getPayUrl(): ?string
    {
        $url = $this->getData(self::PAY_URL);

        return $url === null ? null : (string)$url;
    }

    /**
     * @inheritdoc
     */
    public function setPayUrl(?string $payUrl): void
    {
        $this->setData(self::PAY_URL, $payUrl);
    }

    /**
     * @inheritdoc
     */
    public function getProviderStatus(): ?string
    {
        $status = $this->getData(self::PROVIDER_STATUS);

        return $status === null ? null : (string)$status;
    }

    /**
     * @inheritdoc
     */
    public function setProviderStatus(?string $providerStatus): void
    {
        $this->setData(self::PROVIDER_STATUS, $providerStatus);
    }

    /**
     * @inheritdoc
     */
    public function getPaymentStatus(): string
    {
        return (string)($this->getData(self::PAYMENT_STATUS) ?: self::STATUS_INITIATED);
    }

    /**
     * @inheritdoc
     */
    public function setPaymentStatus(string $paymentStatus): void
    {
        $this->setData(self::PAYMENT_STATUS, $paymentStatus);
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
        return (string)($this->getData(self::CURRENCY) ?: self::CURRENCY_VND);
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
    public function getContractHash(): ?string
    {
        $hash = $this->getData(self::CONTRACT_HASH);

        return $hash === null ? null : (string)$hash;
    }

    /**
     * @inheritdoc
     */
    public function setContractHash(?string $contractHash): void
    {
        $this->setData(self::CONTRACT_HASH, $contractHash);
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
    public function getRequiresReconciliation(): bool
    {
        return (bool)$this->getData(self::REQ_RECONCILIATION);
    }

    /**
     * @inheritdoc
     */
    public function setRequiresReconciliation(bool $requiresReconciliation): void
    {
        $this->setData(self::REQ_RECONCILIATION, $requiresReconciliation);
    }

    /**
     * @inheritdoc
     */
    public function getReconciliationCode(): ?string
    {
        $code = $this->getData(self::RECONCILIATION_CODE);

        return $code === null ? null : (string)$code;
    }

    /**
     * @inheritdoc
     */
    public function setReconciliationCode(?string $reconciliationCode): void
    {
        $this->setData(self::RECONCILIATION_CODE, $reconciliationCode);
    }

    /**
     * @inheritdoc
     */
    public function getRetryCount(): int
    {
        return (int)$this->getData(self::RETRY_COUNT);
    }

    /**
     * @inheritdoc
     */
    public function setRetryCount(int $retryCount): void
    {
        $this->setData(self::RETRY_COUNT, $retryCount);
    }

    /**
     * Get how much proactive v2/query recovery budget this attempt has consumed.
     *
     * @return int
     */
    public function getRecoveryAttempts(): int
    {
        return (int)$this->getData(self::RECOVERY_ATTEMPTS);
    }

    /**
     * Set the recovery budget consumed.
     *
     * @param int $recoveryAttempts
     * @return void
     */
    public function setRecoveryAttempts(int $recoveryAttempts): void
    {
        $this->setData(self::RECOVERY_ATTEMPTS, $recoveryAttempts);
    }

    /**
     * Whether proactive recovery queries are exhausted for this attempt
     * (operational marker only — an authenticated IPN/Return arriving later
     * still resolves the payment).
     *
     * @return bool
     */
    public function isRecoveryExhausted(): bool
    {
        return (bool)$this->getData(self::RECOVERY_EXHAUSTED);
    }

    /**
     * Set the recovery-exhausted operational marker.
     *
     * @param bool $recoveryExhausted
     * @return void
     */
    public function setRecoveryExhausted(bool $recoveryExhausted): void
    {
        $this->setData(self::RECOVERY_EXHAUSTED, $recoveryExhausted);
    }

    /**
     * @inheritdoc
     */
    public function getStoreId(): int
    {
        return (int)$this->getData(self::STORE_ID);
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
    public function getEmailDispatch(): ?int
    {
        $claim = $this->getData(self::EMAIL_DISPATCH);

        return $claim === null ? null : (int)$claim;
    }

    /**
     * @inheritdoc
     */
    public function setEmailDispatch(?int $emailDispatch): void
    {
        $this->setData(self::EMAIL_DISPATCH, $emailDispatch);
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt(): ?string
    {
        $ts = $this->getData(self::CREATED_AT);

        return $ts === null ? null : (string)$ts;
    }

    /**
     * @inheritdoc
     */
    public function setCreatedAt(?string $createdAt): void
    {
        $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritdoc
     */
    public function getUpdatedAt(): ?string
    {
        $ts = $this->getData(self::UPDATED_AT);

        return $ts === null ? null : (string)$ts;
    }

    /**
     * @inheritdoc
     */
    public function setUpdatedAt(?string $updatedAt): void
    {
        $this->setData(self::UPDATED_AT, $updatedAt);
    }

    /**
     * @inheritdoc
     */
    public function getExpiresAt(): ?string
    {
        $ts = $this->getData(self::EXPIRES_AT);

        return $ts === null ? null : (string)$ts;
    }

    /**
     * @inheritdoc
     */
    public function setExpiresAt(?string $expiresAt): void
    {
        $this->setData(self::EXPIRES_AT, $expiresAt);
    }

    /**
     * @inheritdoc
     */
    public function canTransitionTo(string $status): bool
    {
        $allowed = self::TRANSITIONS[$this->getPaymentStatus()] ?? [];

        return in_array($status, $allowed, true);
    }

    /**
     * @inheritdoc
     */
    public function isTerminal(): bool
    {
        return self::TRANSITIONS[$this->getPaymentStatus()] === [];
    }

    /**
     * @inheritdoc
     */
    public function isReusable(?string $now = null): bool
    {
        return $this->getPaymentStatus() === self::STATUS_ACTIVE
            && (string)$this->getPayUrl() !== ''
            && !$this->isExpired($now);
    }

    /**
     * @inheritdoc
     */
    public function isExpired(?string $now = null): bool
    {
        if ($this->getExpiresAt() === null) {
            return false;
        }
        $nowTs = $now !== null ? strtotime($now) : time();

        return $nowTs !== false && strtotime($this->getExpiresAt()) < $nowTs;
    }

    /**
     * @inheritdoc
     */
    public function markActive(string $payUrl): static
    {
        $this->applyTransition(self::STATUS_ACTIVE);
        $this->setPayUrl($payUrl);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function markFailed(string $errorMessage, ?string $providerStatus = 'failed'): static
    {
        $this->applyTransition(self::STATUS_FAILED);
        $this->setLastError($errorMessage);
        if ($providerStatus !== null) {
            $this->setProviderStatus($providerStatus);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function markStale(): static
    {
        return $this->applyTransition(self::STATUS_STALE);
    }

    /**
     * @inheritdoc
     */
    public function markPaid(?string $providerTransactionId = null): static
    {
        $this->applyTransition(self::STATUS_PAID);
        // markPaid(null) never overwrites a recorded provider id.
        if ($providerTransactionId !== null && $providerTransactionId !== '') {
            $this->setProviderTransactionId($providerTransactionId);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function markFinalized(int $orderId): static
    {
        $this->applyTransition(self::STATUS_FINALIZED);
        $this->setOrderId($orderId);

        return $this;
    }

    /**
     * Apply a guarded lifecycle transition.
     *
     * @param string $status
     * @return $this
     * @throws LocalizedException On illegal transition.
     */
    private function applyTransition(string $status): static
    {
        if (!$this->canTransitionTo($status)) {
            throw new LocalizedException(
                __(
                    'Illegal MoMo payment attempt transition "%1" -> "%2".',
                    [$this->getPaymentStatus(), $status]
                )
            );
        }
        $this->setPaymentStatus($status);

        return $this;
    }
}
