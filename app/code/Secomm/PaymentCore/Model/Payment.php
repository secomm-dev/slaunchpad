<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Model;

use Magento\Framework\Model\AbstractModel;
use Secomm\PaymentCore\Api\Data\PaymentInterface;

/**
 * FEAT-CSWYEJ — one lifecycle record per order (UNIQUE order_id).
 */
class Payment extends AbstractModel implements PaymentInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'secomm_paymentcore_payment';

    /**
     * @var string
     */
    protected $_eventObject = 'payment';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(\Secomm\PaymentCore\Model\ResourceModel\Payment::class);
    }

    /**
     * Parent AbstractModel::setEntityId($entityId) declares an untyped
     * parameter — narrowing it to ?int here is a PHP compile error. The
     * untyped concrete signature still satisfies PaymentInterface (an
     * untyped parameter accepts ?int).
     */
    public function setEntityId($entityId): PaymentInterface
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    public function getEntityId(): ?int
    {
        $id = $this->getData(self::ENTITY_ID);
        return $id === null ? null : (int)$id;
    }

    public function getOrderId(): int
    {
        return (int)$this->getData(self::ORDER_ID);
    }

    public function setOrderId(int $orderId): PaymentInterface
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    public function getStoreId(): int
    {
        return (int)$this->getData(self::STORE_ID);
    }

    public function setStoreId(int $storeId): PaymentInterface
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    public function getMethodCode(): string
    {
        return (string)$this->getData(self::METHOD_CODE);
    }

    public function setMethodCode(string $methodCode): PaymentInterface
    {
        return $this->setData(self::METHOD_CODE, $methodCode);
    }

    public function getExpiresAt(): string
    {
        return (string)$this->getData(self::EXPIRES_AT);
    }

    public function setExpiresAt(string $expiresAt): PaymentInterface
    {
        return $this->setData(self::EXPIRES_AT, $expiresAt);
    }

    public function getStatus(): string
    {
        return (string)$this->getData(self::STATUS) ?: self::STATUS_ACTIVE;
    }

    public function setStatus(string $status): PaymentInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getRetryCount(): int
    {
        return (int)$this->getData(self::RETRY_COUNT);
    }

    public function setRetryCount(int $count): PaymentInterface
    {
        return $this->setData(self::RETRY_COUNT, $count);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt(?string $createdAt): PaymentInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setUpdatedAt(?string $updatedAt): PaymentInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    public function getResolvedAt(): ?string
    {
        return $this->getData(self::RESOLVED_AT);
    }

    public function setResolvedAt(?string $resolvedAt): PaymentInterface
    {
        return $this->setData(self::RESOLVED_AT, $resolvedAt);
    }
}
