<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model;

use Magento\Framework\Model\AbstractModel;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentExport as ResourceModel;

/**
 * Mapping row Magento order ↔ external OMS order (SLP-30 FFC-001).
 */
class FulfillmentExport extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }

    public function getMagentoOrderId(): int
    {
        return (int) $this->getData('magento_order_id');
    }

    public function setMagentoOrderId(int $id): self
    {
        return $this->setData('magento_order_id', $id);
    }

    public function getMagentoIncrementId(): string
    {
        return (string) $this->getData('magento_increment_id');
    }

    public function setMagentoIncrementId(string $incrementId): self
    {
        return $this->setData('magento_increment_id', $incrementId);
    }

    public function getServiceCode(): string
    {
        return (string) $this->getData('service_code');
    }

    public function setServiceCode(string $code): self
    {
        return $this->setData('service_code', $code);
    }

    public function getExternalOrderId(): ?string
    {
        $value = $this->getData('external_order_id');
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setExternalOrderId(?string $externalOrderId): self
    {
        return $this->setData('external_order_id', $externalOrderId);
    }

    public function getOrigin(): string
    {
        return (string) $this->getData('origin');
    }

    public function setOrigin(string $origin): self
    {
        return $this->setData('origin', $origin);
    }

    public function getPushStatus(): string
    {
        return (string) $this->getData('push_status');
    }

    public function setPushStatus(string $status): self
    {
        return $this->setData('push_status', $status);
    }

    public function getAttemptCount(): int
    {
        return (int) $this->getData('attempt_count');
    }

    public function setAttemptCount(int $count): self
    {
        return $this->setData('attempt_count', $count);
    }

    public function getLastError(): ?string
    {
        $value = $this->getData('last_error');
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setLastError(?string $error): self
    {
        return $this->setData('last_error', $error);
    }

    public function getLastPushedAt(): ?string
    {
        $value = $this->getData('last_pushed_at');
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setLastPushedAt(?string $datetime): self
    {
        return $this->setData('last_pushed_at', $datetime);
    }
}
