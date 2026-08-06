<?php

namespace Secomm\Ahamove\Model\ResourceModel\AhamoveCity;

use Secomm\Ahamove\Model\AhamoveCity as Model;
use Secomm\Ahamove\Model\ResourceModel\AhamoveCity as ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class AhamoveCityCollection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'ahamove_city_collection';

    /**
     * Initialize collection model.
     */
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
