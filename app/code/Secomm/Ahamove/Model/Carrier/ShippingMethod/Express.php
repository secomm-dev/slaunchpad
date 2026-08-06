<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model\Carrier\ShippingMethod;

use Secomm\Ahamove\Model\Config;

class Express extends AhamoveShippingMethod
{
    /**
     * Carrier's code
     *
     * @var string
     */
    const AHAMOVE_EXPRESS_CARRIER_CODE = 'ahamove_express';

    /**
     * @var string
     */
    protected $_code = self::AHAMOVE_EXPRESS_CARRIER_CODE;

    public function getService()
    {
        return \Secomm\Ahamove\Model\Carrier\ShippingMethod\AhamoveShippingMethod::GROUP_EXPRESS;
    }
}
