<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model;

use Magento\Framework\Model\AbstractModel;
use Secomm\Tracking\Model\ResourceModel\DeliveryLog as DeliveryLogResource;

/**
 * FEAT-31X6N2 — one delivery-log row per vendor send attempt (masked summary only).
 */
class DeliveryLog extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(DeliveryLogResource::class);
    }
}
