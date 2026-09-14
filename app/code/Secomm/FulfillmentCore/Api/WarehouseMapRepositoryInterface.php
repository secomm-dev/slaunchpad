<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Api;

use Secomm\FulfillmentCore\Api\Data\WarehouseMapInterface;
use Secomm\FulfillmentCore\Model\Warehouse\WarehouseMapConflictException;

/**
 * Persist MSI ↔ external warehouse maps scoped by service_code.
 */
interface WarehouseMapRepositoryInterface
{
    /**
     * @param int $entityId Map row id
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $entityId): WarehouseMapInterface;

    /**
     * @param WarehouseMapInterface $map Map DTO / model to persist
     * @throws WarehouseMapConflictException When UNIQUE (service, source) or (service, warehouse) conflicts
     */
    public function save(WarehouseMapInterface $map): WarehouseMapInterface;

    /**
     * @param WarehouseMapInterface $map Existing map row
     */
    public function delete(WarehouseMapInterface $map): void;

    /**
     * @param string $serviceCode Adapter service_code
     * @return WarehouseMapInterface[]
     */
    public function getListByService(string $serviceCode): array;
}
