<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\ZaloPay\Model\ResourceModel;

use Secomm\ZaloPay\Api\Data\RefundInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class RefundResource extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'zalo_pay_refund_resource_model';

    /**
     * Initialize resource model.
     */
    protected function _construct()
    {
        $this->_init('zalo_pay_refund', RefundInterface::ENTITY_ID);
        $this->_useIsObjectNew = true;
    }
}
