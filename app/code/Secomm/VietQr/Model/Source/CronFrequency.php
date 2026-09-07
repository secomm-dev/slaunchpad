<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\VietQr\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Preset cron intervals for the VietQR auto-cancel job (TASK-6X2FQH / AC-024).
 *
 * Option values are literal cron expressions — the field saves them straight
 * to the job's crontab config path, so no code↔expression conversion exists.
 * A raw expression field was rejected: one invalid value would silently kill
 * the schedule, preset values cannot be invalid.
 */
class CronFrequency implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '*/5 * * * *', 'label' => __('Every 5 minutes')],
            ['value' => '*/10 * * * *', 'label' => __('Every 10 minutes')],
            ['value' => '*/15 * * * *', 'label' => __('Every 15 minutes')],
            ['value' => '*/30 * * * *', 'label' => __('Every 30 minutes')],
            ['value' => '0 * * * *', 'label' => __('Every 60 minutes')],
        ];
    }
}
