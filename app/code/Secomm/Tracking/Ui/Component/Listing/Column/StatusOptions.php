<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\Tracking\Model\TrackingEventQueue;

/**
 * FEAT-31X6N2 — outbox status filter options.
 */
class StatusOptions implements OptionSourceInterface
{
    /**
     * @return array<int, array<string, string>>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => TrackingEventQueue::STATUS_PENDING, 'label' => __('Pending')],
            ['value' => TrackingEventQueue::STATUS_SENT, 'label' => __('Sent')],
            ['value' => TrackingEventQueue::STATUS_FAILED, 'label' => __('Failed')],
            ['value' => TrackingEventQueue::STATUS_SKIPPED, 'label' => __('Skipped')],
        ];
    }
}
