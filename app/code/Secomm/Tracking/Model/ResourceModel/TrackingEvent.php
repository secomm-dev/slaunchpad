<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * FEAT-31X6N2 — outbox resource (secomm_tracking_event).
 */
class TrackingEvent extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('secomm_tracking_event', 'entity_id');
    }
}
