<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Secomm\Tracking\Model\ResourceModel\TrackingEvent as TrackingEventResource;

/**
 * FEAT-31X6N2 — outbox row model. Observers enqueue; the flush cron delivers.
 *
 * Status lifecycle: pending → sent | failed | skipped.
 * payload holds the normalized TrackingEvent JSON (identifiers pre-hashed).
 */
class TrackingEventQueue extends AbstractModel implements IdentityInterface
{
    public const CACHE_TAG = 'secomm_tracking_event';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected function _construct(): void
    {
        $this->_init(TrackingEventResource::class);
    }

    /**
     * @return string[]
     */
    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}
