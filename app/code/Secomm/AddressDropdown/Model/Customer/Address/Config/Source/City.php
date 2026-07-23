<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Customer\Address\Config\Source;


use Secomm\AddressDropdown\Helper\Address;

class City extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{

    /** @var Address  */
    protected $addressHelper;

    public function __construct(
        Address $addressHelper
    )   {
        $this->addressHelper = $addressHelper;
    }

    /**
     * getAllOptions
     *
     * @return array
     */
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $cityData = $this->addressHelper->getCityData();
            foreach ($cityData as $key => $city) {
                $this->_options[] = ['value' => $city['default_name'], 'label' => $city['name']];
            }
        }
        return $this->_options;
    }
}

