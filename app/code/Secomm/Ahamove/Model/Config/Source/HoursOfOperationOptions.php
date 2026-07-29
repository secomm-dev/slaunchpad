<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class HoursOfOperationOptions implements ArrayInterface
{
    public function toOptionArray()
    {
        $options = [];
        for ($hour = 0; $hour < 24; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 30) {
                $timeValue = sprintf('%02d-%02d', $hour, $minute);
                $timeLabel = sprintf('%02d:%02d', $hour, $minute);
                $options[] = ['value' => $timeValue, 'label' => __($timeLabel)];
            }
        }
        return $options;
    }
}
