<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Api;

use Secomm\FulfillmentCore\Api\Data\WarehouseMapInterface;

/**
 * Resolve MSI source → external warehouse map for a given OMS service.
 */
interface WarehouseMapResolverInterface
{
    /**
     * @param string $serviceCode Adapter service_code (e.g. pancake)
     * @param string $magentoSourceCode MSI inventory_source.source_code
     * @return WarehouseMapInterface|null Active map row, or null if unmapped
     */
    public function resolve(string $serviceCode, string $magentoSourceCode): ?WarehouseMapInterface;
}
