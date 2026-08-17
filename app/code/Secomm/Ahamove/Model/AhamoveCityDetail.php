<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model;

use Magento\Framework\Model\AbstractModel;
use Secomm\Ahamove\Model\ResourceModel\AhamoveCityDetail as ResourceModel;

class AhamoveCityDetail extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'ahamove_city_detail_model';

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
