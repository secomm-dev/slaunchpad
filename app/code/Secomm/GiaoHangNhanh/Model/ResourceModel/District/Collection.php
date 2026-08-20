<?php declare(strict_types=1);

namespace Secomm\GiaoHangNhanh\Model\ResourceModel\District;

use Secomm\GiaoHangNhanh\Model\ResourceModel\District as DistrictResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \Magento\Framework\Model\AbstractModel::class,
            DistrictResource::class
        );
    }
}
