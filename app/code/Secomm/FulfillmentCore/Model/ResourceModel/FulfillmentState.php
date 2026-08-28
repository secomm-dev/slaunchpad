<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class FulfillmentState extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('secomm_fulfillment_state', 'entity_id');
    }
}
