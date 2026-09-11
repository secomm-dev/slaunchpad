<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Api\Data;

/**
 * Magento order status ↔ external POS status map row (same idea as warehouse map).
 */
interface StatusMapInterface
{
    public function getEntityId(): ?int;

    public function setEntityId(?int $entityId): self;

    public function getServiceCode(): string;

    public function setServiceCode(string $serviceCode): self;

    public function getMagentoOrderStatus(): string;

    public function setMagentoOrderStatus(string $magentoOrderStatus): self;

    public function getExternalStatusCode(): string;

    public function setExternalStatusCode(string $statusCode): self;

    public function getExternalStatusLabel(): ?string;

    public function setExternalStatusLabel(?string $label): self;

    public function getIsActive(): bool;

    public function setIsActive(bool $isActive): self;
}
