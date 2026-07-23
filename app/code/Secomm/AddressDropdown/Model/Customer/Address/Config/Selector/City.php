<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Customer\Address\Config\Selector;

use Magento\Directory\Model\Region;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollectionFactory as CollectionFactory;

class City extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    /** @var CollectionFactory  */
    protected $cityCollectionFactory;

    /** @var Region */
    protected $region;

    public function __construct(
        CollectionFactory $cityCollectionFactory,
        Region $region
    )   {
        $this->cityCollectionFactory = $cityCollectionFactory;
        $this->region = $region;
    }

    /**
     * getAllOptions
     *
     * @return array
     */
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options[] = ['value' => '', 'label' => __('Please select the city')];
        }
        return $this->_options;
    }
}
