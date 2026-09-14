<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Api\Data;

/**
 * Lightweight warehouse map DTO (also implemented by FulfillmentWarehouseMap model).
 */
class WarehouseMapDto implements WarehouseMapInterface
{
    private ?int $entityId = null;
    private string $serviceCode = '';
    private string $magentoSourceCode = '';
    private string $externalWarehouseId = '';
    private ?string $externalWarehouseLabel = null;
    private bool $isActive = true;

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(?int $entityId): WarehouseMapInterface
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getServiceCode(): string
    {
        return $this->serviceCode;
    }

    public function setServiceCode(string $serviceCode): WarehouseMapInterface
    {
        $this->serviceCode = $serviceCode;
        return $this;
    }

    public function getMagentoSourceCode(): string
    {
        return $this->magentoSourceCode;
    }

    public function setMagentoSourceCode(string $sourceCode): WarehouseMapInterface
    {
        $this->magentoSourceCode = $sourceCode;
        return $this;
    }

    public function getExternalWarehouseId(): string
    {
        return $this->externalWarehouseId;
    }

    public function setExternalWarehouseId(string $warehouseId): WarehouseMapInterface
    {
        $this->externalWarehouseId = $warehouseId;
        return $this;
    }

    public function getExternalWarehouseLabel(): ?string
    {
        return $this->externalWarehouseLabel;
    }

    public function setExternalWarehouseLabel(?string $label): WarehouseMapInterface
    {
        $this->externalWarehouseLabel = $label;
        return $this;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): WarehouseMapInterface
    {
        $this->isActive = $isActive;
        return $this;
    }
}
