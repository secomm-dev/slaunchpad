<?php declare(strict_types=1);

namespace Secomm\GiaoHangNhanh\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Province extends AbstractDb
{
    const TABLE_NAME = 'secomm_giaohangnhanh_province';
    const ID_FIELD = 'entity_id';

    protected function _construct()
    {
        $this->_init(self::TABLE_NAME, self::ID_FIELD);
    }

    /**
     * @param int $provinceId
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function existsByProvinceId(int $provinceId): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), self::ID_FIELD)
            ->where('province_id = ?', $provinceId);
        return (bool) $connection->fetchOne($select);
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAll(): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()->from($this->getMainTable());
        return $connection->fetchAll($select);
    }

    /**
     * @param int $provinceId
     * @return string|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getProvinceNameById(int $provinceId): ?string
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), 'province_name')
            ->where('province_id = ?', $provinceId);
        return $connection->fetchOne($select) ?: null;
    }

    /**
     * @param int $provinceId
     * @return array|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getByProvinceId(int $provinceId): ?array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('province_id = ?', $provinceId);
        $result = $connection->fetchRow($select);
        return $result ?: null;
    }
}
