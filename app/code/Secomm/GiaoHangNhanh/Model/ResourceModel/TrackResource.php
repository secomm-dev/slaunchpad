<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\GiaoHangNhanh\Model\ResourceModel;

use Secomm\GiaoHangNhanh\Api\Data\TrackInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class TrackResource extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'ghn_webhook_track_resource_model';

    /**
     * Initialize resource model.
     */
    protected function _construct()
    {
        $this->_init('ghn_webhook_track', TrackInterface::ENTITY_ID);
        $this->_useIsObjectNew = true;
    }
}
