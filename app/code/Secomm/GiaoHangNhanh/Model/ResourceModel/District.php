<?php
declare(strict_types=1);

namespace Secomm\GiaoHangNhanh\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class District extends AbstractDb
{
    const TABLE_NAME = 'secomm_giaohangnhanh_district';
    const ID_FIELD = 'entity_id';

    protected function _construct()
    {
        $this->_init(self::TABLE_NAME, self::ID_FIELD);
    }

    public function getDistrictsWithRegion(): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(['districtTable' => $this->getMainTable()])
            ->joinInner(
                ['provinceTable' => $this->getTable('secomm_giaohangnhanh_province')],
                'provinceTable.province_id = districtTable.province_id',
                ['province_name']
            );
        return $connection->fetchAll($select);
    }

    public function existsByDistrictId(int $districtId): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), self::ID_FIELD)
            ->where('district_id = ?', $districtId);
        return (bool) $connection->fetchOne($select);
    }
}
