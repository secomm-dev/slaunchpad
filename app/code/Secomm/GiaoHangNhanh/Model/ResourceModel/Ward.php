<?php declare(strict_types=1);

namespace Secomm\GiaoHangNhanh\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Ward extends AbstractDb
{
    const TABLE_NAME = 'secomm_giaohangnhanh_ward';
    const ID_FIELD = 'entity_id';

    protected function _construct()
    {
        $this->_init(self::TABLE_NAME, self::ID_FIELD);
    }

    public function getDistricts(): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable('secomm_giaohangnhanh_district'));
        return $connection->fetchAll($select);
    }

    public function existsByWardCode(int $wardCode): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), self::ID_FIELD)
            ->where('ward_code = ?', $wardCode);
        return (bool) $connection->fetchOne($select);
    }

    public function insertIfNotExists(array $data, int $wardCode): bool
    {
        if ($this->existsByWardCode($wardCode)) {
            return false;
        }
        $this->getConnection()->insert($this->getMainTable(), $data);
        return true;
    }

    public function insertBatch(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }
        $connection = $this->getConnection();
        return (int) $connection->insertOnDuplicate(
            $this->getMainTable(),
            $rows,
            ['ward_name', 'district_id']
        );
    }

    public function hasWardsForDistrict(int $districtEntityId): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), self::ID_FIELD)
            ->where('district_id = ?', $districtEntityId)
            ->limit(1);
        return (bool) $connection->fetchOne($select);
    }
}
