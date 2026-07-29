<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class OperationDayOptions implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'monday', 'label' => __('Monday')],
            ['value' => 'tuesday', 'label' => __('Tuesday')],
            ['value' => 'wednesday', 'label' => __('Wednesday')],
            ['value' => 'thursday', 'label' => __('Thursday')],
            ['value' => 'friday', 'label' => __('Friday')],
            ['value' => 'saturday', 'label' => __('Saturday')],
            ['value' => 'sunday', 'label' => __('Sunday')],
        ];
    }
}
