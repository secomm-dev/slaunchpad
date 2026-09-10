<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentStatusMap;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\FulfillmentCore\Model\FulfillmentStatusMap as Model;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentStatusMap as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
