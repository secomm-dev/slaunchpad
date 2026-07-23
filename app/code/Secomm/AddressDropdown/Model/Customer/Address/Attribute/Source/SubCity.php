<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Customer\Address\Attribute\Source;

use Secomm\AddressDropdown\Model\ResourceModel\SubCityModel\SubCityCollectionFactory;

class SubCity extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    /**
     * @var SubCityCollectionFactory
     **/
    protected SubCityCollectionFactory $subCityCollectionFactory;

    /**
     * @param SubCityCollectionFactory $subCityCollectionFactory
     */
    public function __construct(
        SubCityCollectionFactory $subCityCollectionFactory
    )
    {
        $this->subCityCollectionFactory = $subCityCollectionFactory;
    }

    /**
     * getAllOptions
     *
     * @return array
     */
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $subCities = $this->subCityCollectionFactory->create()->getData();
            foreach ($subCities as $key => $subCity) {
                $this->_options[] = ['value' => $subCity['sub_city_id'], 'label' => $subCity['default_name']];
            }
        }

        return !is_null($this->_options) ? $this->_options : [];
    }
}

