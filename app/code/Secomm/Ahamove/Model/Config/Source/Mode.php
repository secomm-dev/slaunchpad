<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model\Config\Source;

class Mode implements \Magento\Framework\Option\ArrayInterface
{
    const SANDBOX_MODE = 'sandbox';
    const PRODUCTION_MODE = 'production';

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [['value' => self::SANDBOX_MODE, 'label' => __('Staging')], ['value' => self::PRODUCTION_MODE, 'label' => __('Production')]];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [self::PRODUCTION_MODE => __('Production'), self::SANDBOX_MODE => __('Staging')];
    }

}
