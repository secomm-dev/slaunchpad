<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Boolfly\GiaoHangNhanh\Model;

use Boolfly\GiaoHangNhanh\Model\ResourceModel\TrackResource;
use Magento\Framework\Model\AbstractModel;

class TrackModel extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'ghn_webhook_track_model';

    /**
     * Initialize magento model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(TrackResource::class);
    }
}
