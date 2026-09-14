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
 * Creates FulfillmentStatusMap model instances.
 */
class FulfillmentStatusMapFactory
{
    public function __construct(
        private readonly ObjectManagerInterface $objectManager
    ) {
    }

    /**
     * @param array<string, mixed> $data Constructor data
     */
    public function create(array $data = []): FulfillmentStatusMap
    {
        return $this->objectManager->create(FulfillmentStatusMap::class, $data);
    }
}
