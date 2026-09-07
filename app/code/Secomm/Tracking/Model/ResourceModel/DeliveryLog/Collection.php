<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model\ResourceModel\DeliveryLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\Tracking\Model\DeliveryLog as DeliveryLogModel;
use Secomm\Tracking\Model\ResourceModel\DeliveryLog as DeliveryLogResource;

/**
 * FEAT-31X6N2 — delivery log collection (admin grid).
 */
class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(DeliveryLogModel::class, DeliveryLogResource::class);
    }
}
