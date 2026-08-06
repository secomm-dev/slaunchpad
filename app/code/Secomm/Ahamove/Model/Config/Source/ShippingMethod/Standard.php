<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\Ahamove\Model\Config\Source\ShippingMethod;

class Standard implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var \Secomm\Ahamove\Model\Carrier\ShippingMethod\Standard
     */
    protected $carrier;

    /**
     * @param \Secomm\Ahamove\Model\Carrier\ShippingMethod\Standard $carrier
     */
    public function __construct(
        \Secomm\Ahamove\Model\Carrier\ShippingMethod\Standard $carrier
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
