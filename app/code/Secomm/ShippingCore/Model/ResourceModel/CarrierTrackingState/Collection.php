<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\ResourceModel\CarrierTrackingState;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\ShippingCore\Model\CarrierTrackingState;
use Secomm\ShippingCore\Model\ResourceModel\CarrierTrackingState as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(CarrierTrackingState::class, ResourceModel::class);
    }
}
