<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model\ResourceModel\TrackingEvent;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\Tracking\Model\ResourceModel\TrackingEvent as TrackingEventResource;
use Secomm\Tracking\Model\TrackingEventQueue as TrackingEventQueueModel;

/**
 * FEAT-31X6N2 — outbox collection (cron flush consumer: TASK-VRKJKQ).
 */
class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(TrackingEventQueueModel::class, TrackingEventResource::class);
    }
}