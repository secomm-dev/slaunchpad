<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Api;

use Secomm\FulfillmentCore\Api\Data\StatusMapInterface;

/**
 * Resolve vendor raw status → normalized status map for a given OMS service.
 */
interface StatusMapResolverInterface
{
    /**
     * @param string $serviceCode Adapter service_code (e.g. pancake)
     * @param string $externalStatusCode Vendor raw status code
     * @return StatusMapInterface|null Active map row, or null if unmapped
     */
    public function resolve(string $serviceCode, string $externalStatusCode): ?StatusMapInterface;
}
