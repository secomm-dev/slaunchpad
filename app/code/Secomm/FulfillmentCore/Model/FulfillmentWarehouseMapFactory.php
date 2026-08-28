<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model;

use Magento\Framework\ObjectManagerInterface;

/**
 * Creates FulfillmentWarehouseMap model instances.
 */
class FulfillmentWarehouseMapFactory
{
    public function __construct(
        private readonly ObjectManagerInterface $objectManager
    ) {
    }

    /**
     * @param array<string, mixed> $data Constructor data
     */
    public function create(array $data = []): FulfillmentWarehouseMap
    {
        return $this->objectManager->create(FulfillmentWarehouseMap::class, $data);
    }
}
