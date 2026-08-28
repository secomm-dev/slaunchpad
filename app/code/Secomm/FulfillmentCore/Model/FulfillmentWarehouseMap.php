<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model;

use Magento\Framework\Model\AbstractModel;
use Secomm\FulfillmentCore\Api\Data\WarehouseMapInterface;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentWarehouseMap as ResourceModel;

/**
 * MSI source ↔ external warehouse map model (SLP-30 FFC-004).
 */
class FulfillmentWarehouseMap extends AbstractModel implements WarehouseMapInterface
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

    public function setEntityId($entityId): WarehouseMapInterface
    {
        return $this->setData('entity_id', $entityId);
    }

    public function getServiceCode(): string
    {
        return (string) $this->getData('service_code');
    }

    public function setServiceCode(string $serviceCode): WarehouseMapInterface
    {
        return $this->setData('service_code', $serviceCode);
    }

    public function getMagentoSourceCode(): string
    {
        return (string) $this->getData('magento_source_code');
    }

    public function setMagentoSourceCode(string $sourceCode): WarehouseMapInterface
    {
        return $this->setData('magento_source_code', $sourceCode);
    }

    public function getExternalWarehouseId(): string
    {
        return (string) $this->getData('external_warehouse_id');
    }

    public function setExternalWarehouseId(string $warehouseId): WarehouseMapInterface
    {
        return $this->setData('external_warehouse_id', $warehouseId);
    }

    public function getExternalWarehouseLabel(): ?string
    {
        $value = $this->getData('external_warehouse_label');
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setExternalWarehouseLabel(?string $label): WarehouseMapInterface
    {
        return $this->setData('external_warehouse_label', $label);
    }

    public function getIsActive(): bool
    {
        return (bool) (int) $this->getData('is_active');
    }

    public function setIsActive(bool $isActive): WarehouseMapInterface
    {
        return $this->setData('is_active', $isActive ? 1 : 0);
    }
}
