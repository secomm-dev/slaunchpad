<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Model\Data;

use Magento\Framework\DataObject;
use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;

class IssueRequest extends DataObject implements IssueRequestInterface
{
    /**
     * @inheritdoc
     */
    public function getOrderId(): int
    {
        return (int) $this->getData(self::ORDER_ID);
    }

    /**
     * @inheritdoc
     */
    public function setOrderId(int $orderId): IssueRequestInterface
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * @inheritdoc
     */
    public function getOrderIncrementId(): string
    {
        return (string) $this->getData(self::ORDER_INCREMENT_ID);
    }

    /**
     * @inheritdoc
     */
    public function setOrderIncrementId(string $incrementId): IssueRequestInterface
    {
        return $this->setData(self::ORDER_INCREMENT_ID, $incrementId);
    }

    /**
     * @inheritdoc
     */
    public function getStoreId(): int
    {
        return (int) $this->getData(self::STORE_ID);
    }

    /**
     * @inheritdoc
     */
    public function setStoreId(int $storeId): IssueRequestInterface
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @inheritdoc
     */
    public function getPayload(): array
    {
        $payload = $this->getData(self::PAYLOAD);
        return is_array($payload) ? $payload : [];
    }

    /**
     * @inheritdoc
     */
    public function setPayload(array $payload): IssueRequestInterface
    {
        return $this->setData(self::PAYLOAD, $payload);
    }
}
