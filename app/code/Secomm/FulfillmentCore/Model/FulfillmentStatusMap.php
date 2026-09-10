<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model;

use Magento\Framework\Model\AbstractModel;
use Secomm\FulfillmentCore\Api\Data\StatusMapInterface;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentStatusMap as ResourceModel;

/**
 * Magento order status ↔ POS status map model (admin-managed, per service_code).
 */
class FulfillmentStatusMap extends AbstractModel implements StatusMapInterface
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }

    public function getEntityId(): ?int
    {
        $id = $this->getData('entity_id');
        return $id !== null && $id !== '' ? (int) $id : null;
    }

    public function setEntityId($entityId): StatusMapInterface
    {
        return $this->setData('entity_id', $entityId);
    }

    public function getServiceCode(): string
    {
        return (string) $this->getData('service_code');
    }

    public function setServiceCode(string $serviceCode): StatusMapInterface
    {
        return $this->setData('service_code', $serviceCode);
    }

    public function getMagentoOrderStatus(): string
    {
        return (string) $this->getData('magento_order_status');
    }

    public function setMagentoOrderStatus(string $magentoOrderStatus): StatusMapInterface
    {
        return $this->setData('magento_order_status', $magentoOrderStatus);
    }

    public function getExternalStatusCode(): string
    {
        return (string) $this->getData('external_status_code');
    }

    public function setExternalStatusCode(string $statusCode): StatusMapInterface
    {
        return $this->setData('external_status_code', $statusCode);
    }

    public function getExternalStatusLabel(): ?string
    {
        $value = $this->getData('external_status_label');
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setExternalStatusLabel(?string $label): StatusMapInterface
    {
        return $this->setData('external_status_label', $label);
    }

    public function getIsActive(): bool
    {
        return (bool) (int) $this->getData('is_active');
    }

    public function setIsActive(bool $isActive): StatusMapInterface
    {
        return $this->setData('is_active', $isActive ? 1 : 0);
    }
}
