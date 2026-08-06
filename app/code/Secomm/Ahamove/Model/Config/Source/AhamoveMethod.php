<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model\Config\Source;

class AhamoveMethod implements \Magento\Framework\Data\OptionSourceInterface
{
    const TABLE_RATE = 'table_rate';
    const API_SHIPPING = 'api_shipping';


    public function toOptionArray(): array
    {
        return [
            ['value' => self::TABLE_RATE, 'label' => __('Table Rate')],
            ['value' => self::API_SHIPPING, 'label' => __('API Shipping')]
        ];
    }
}
