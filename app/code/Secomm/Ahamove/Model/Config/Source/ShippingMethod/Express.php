<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\Ahamove\Model\Config\Source\ShippingMethod;

class Express implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var \Secomm\Ahamove\Model\Carrier\ShippingMethod\Express
     */
    protected $carrier;

    /**
     * @param \Secomm\Ahamove\Model\Carrier\ShippingMethod\Express $carrier
     */
    public function __construct(
        \Secomm\Ahamove\Model\Carrier\ShippingMethod\Express $carrier
    ) {
        $this->carrier = $carrier;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $arr = [];
        foreach ($this->carrier->getCode('condition_name') as $k => $v) {
            $arr[] = ['value' => $k, 'label' => $v];
        }
        return $arr;
    }
}
