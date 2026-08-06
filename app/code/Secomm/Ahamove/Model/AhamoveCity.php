<?php

namespace Secomm\Ahamove\Model;

use Secomm\Ahamove\Model\ResourceModel\AhamoveCity as ResourceModel;
use Magento\Framework\Model\AbstractModel;

class AhamoveCity extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'ahamove_city_model';

    /**
     * Initialize magento model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }
}
