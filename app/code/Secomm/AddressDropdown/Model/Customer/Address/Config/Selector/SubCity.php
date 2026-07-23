<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Customer\Address\Config\Selector;


use Secomm\AddressDropdown\Helper\Address;
use Secomm\AddressDropdown\Model\ResourceModel\SubCityModel\SubCityLocaleCollectionFactory as CollectionFactory;
/**
 * Copyright © Secomm DevTeam All rights reserved.
 * See COPYING.txt for license details.
 */
class SubCity extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    /**
     * @var CollectionFactory
     **/
    protected $subCityCollectionFactory;

    /** @var Address  */
    protected $addressHelper;

    public function __construct(
        CollectionFactory $subCityCollectionFactory,
        Address $addressHelper
    )   {
        $this->subCityCollectionFactory = $subCityCollectionFactory;
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
            $this->_options[] = ['value' => '', 'label' => __('Please select the subcity')];
        }
        return $this->_options;
    }
}

