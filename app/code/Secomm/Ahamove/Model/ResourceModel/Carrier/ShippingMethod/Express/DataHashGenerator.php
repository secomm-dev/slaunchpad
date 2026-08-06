<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\Ahamove\Model\ResourceModel\Carrier\ShippingMethod\Express;

class DataHashGenerator
{
    /**
     * @param array $data
     * @return string
     */
    public function getHash(array $data)
    {
        $countryId = $data['dest_country_id'];
        $regionId = $data['dest_region_id'];
        $zipCode = $data['dest_zip'];
        $conditionValue = $data['condition_value'];

        return sprintf("%s-%d-%s-%F", $countryId, $regionId, $zipCode, $conditionValue);
    }
}
