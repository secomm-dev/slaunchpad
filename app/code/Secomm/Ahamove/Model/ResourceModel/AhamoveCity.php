<?php

namespace Secomm\Ahamove\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class AhamoveCity extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'ahamove_city_resource_model';

    /**
     * Initialize resource model.
     */
    protected function _construct()
    {
        $this->_init('ahamove_city', 'entity_id');
        $this->_useIsObjectNew = true;
    }
}
