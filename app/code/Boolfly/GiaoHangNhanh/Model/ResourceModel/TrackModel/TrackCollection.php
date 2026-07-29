<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Boolfly\GiaoHangNhanh\Model\ResourceModel\TrackModel;

use Boolfly\GiaoHangNhanh\Model\ResourceModel\TrackResource;
use Boolfly\GiaoHangNhanh\Model\TrackModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class TrackCollection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'ghn_webhook_track_collection';

    /**
     * Initialize collection model.
     */
    protected function _construct()
    {
        $this->_init(TrackModel::class, TrackResource::class);
    }
}
