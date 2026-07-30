<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\ZaloPay\Model;

use Secomm\ZaloPay\Model\ResourceModel\RefundResource;
use Magento\Framework\Model\AbstractModel;

class RefundModel extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'zalo_pay_refund_model';

    /**
     * Initialize magento model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(RefundResource::class);
    }
}
