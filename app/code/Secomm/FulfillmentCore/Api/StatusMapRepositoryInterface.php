<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Api;

use Secomm\FulfillmentCore\Api\Data\StatusMapInterface;
use Secomm\FulfillmentCore\Model\Status\StatusMapConflictException;

/**
 * Persist vendor raw status ↔ normalized maps scoped by service_code.
 */
interface StatusMapRepositoryInterface
{
    /**
     * @param int $entityId Map row id
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $entityId): StatusMapInterface;

    /**
     * @param StatusMapInterface $map Map DTO / model to persist
     * @throws StatusMapConflictException When UNIQUE (service, external_status_code) conflicts
     */
    public function save(StatusMapInterface $map): StatusMapInterface;

    /**
     * @param StatusMapInterface $map Existing map row
     */
    public function delete(StatusMapInterface $map): void;

    /**
     * @param string $serviceCode Adapter service_code
     * @return StatusMapInterface[]
     */
    public function getListByService(string $serviceCode): array;
}
