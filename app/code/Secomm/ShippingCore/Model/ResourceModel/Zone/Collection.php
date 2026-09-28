<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\ResourceModel\Zone;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\ShippingCore\Model\ResourceModel\Zone as ZoneResource;
use Secomm\ShippingCore\Model\Zone;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — all persisted canonical zones. Deterministic code ASC
 * order everywhere (admin options + registry merge stability).
 */
class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct()
    {
        // No SQL default order here — the connection/select lifecycle is not ready during
        // _construct; deterministic code-ASC ordering lives in ZoneRepository (PHP sort)
        // and in the grid's own sort handling.
        $this->_init(Zone::class, ZoneResource::class);
    }
}
