<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Api\Data;

/**
 * MSI source ↔ external warehouse map row contract.
 */
interface WarehouseMapInterface
{
    public function getEntityId(): ?int;

    public function setEntityId(?int $entityId): self;

    public function getServiceCode(): string;

    public function setServiceCode(string $serviceCode): self;

    public function getMagentoSourceCode(): string;

    public function setMagentoSourceCode(string $sourceCode): self;

    public function getExternalWarehouseId(): string;

    public function setExternalWarehouseId(string $warehouseId): self;

    public function getExternalWarehouseLabel(): ?string;

    public function setExternalWarehouseLabel(?string $label): self;

    public function getIsActive(): bool;

    public function setIsActive(bool $isActive): self;
}
