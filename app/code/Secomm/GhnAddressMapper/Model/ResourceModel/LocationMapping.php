<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class LocationMapping extends AbstractDb
{
    const TABLE_NAME = 'secomm_ghn_address_mapping_location';
    const ID_FIELD = 'entity_id';

    protected function _construct()
    {
        $this->_init(self::TABLE_NAME, self::ID_FIELD);
    }

    /**
     * @param int $regionId
     * @param int $cityId
     * @return array|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function findByAddress(int $regionId, int $cityId): ?array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('region_id = ?', $regionId)
            ->where('city_id = ?', $cityId)
            ->where('status = ?', 1)
            ->order('priority DESC, entity_id DESC')
            ->limit(1);
        $result = $connection->fetchRow($select);
        return $result ?: null;
    }

    /**
     * Status-agnostic lookup by the natural dedup key (city_id + ghn_ward_code).
     * Used by the CSV importer to detect whether a specific mapping already exists.
     *
     * @param int $cityId
     * @param string $ghnWardCode
     * @return array|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function findByMapping(int $cityId, string $ghnWardCode): ?array
    {
        if ($cityId <= 0 || $ghnWardCode === '') {
            return null;
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('city_id = ?', $cityId)
            ->where('ghn_ward_code = ?', $ghnWardCode)
            ->limit(1);
        $result = $connection->fetchRow($select);
        return $result ?: null;
    }

    /**
     * Resolve the locale-independent city_id from a city name.
     *
     * Tries the default_name first (the value actually persisted on quote/order
     * addresses at runtime), then falls back to the locale-name table to cover
     * names imported while a non-default locale was active.
     *
     * @param int $regionId
     * @param string $cityName
     * @return int|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCityIdByName(int $regionId, string $cityName): ?int
    {
        if ($cityName === '') {
            return null;
        }
        $connection = $this->getConnection();

        // Pass 1: default_name within the same region.
        $select = $connection->select()
            ->from($this->getTable('directory_region_city'), 'city_id')
            ->where('region_id = ?', $regionId)
            ->where('default_name = ?', $cityName)
            ->limit(1);
        $cityId = $connection->fetchOne($select);
        if ($cityId) {
            return (int)$cityId;
        }

        // Pass 2: locale-specific name.
        $select = $connection->select()
            ->from(['cn' => $this->getTable('directory_region_city_name')], 'cn.city_id')
            ->joinInner(
                ['c' => $this->getTable('directory_region_city')],
                'cn.city_id = c.city_id',
                []
            )
            ->where('c.region_id = ?', $regionId)
            ->where('cn.name = ?', $cityName)
            ->limit(1);
        $cityId = $connection->fetchOne($select);
        return $cityId ? (int)$cityId : null;
    }

    /**
     * Resolve a display city name (current locale, falling back to default_name) from a city_id.
     *
     * @param int $cityId
     * @param string $locale
     * @return string|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCityNameById(int $cityId, string $locale = 'vi_VN'): ?string
    {
        if (!$cityId) {
            return null;
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(['c' => $this->getTable('directory_region_city')], [])
            ->joinLeft(
                ['cn' => $this->getTable('directory_region_city_name')],
                $connection->quoteInto('c.city_id = cn.city_id AND cn.locale = ?', $locale),
                ['city_name' => 'COALESCE(cn.name, c.default_name)']
            )
            ->where('c.city_id = ?', $cityId);

        return $connection->fetchOne($select) ?: null;
    }

    public function getRegionNameById(int $regionId, string $locale = 'vi_VN'): ?string
    {
        if (!$regionId) {
            return null;
        }
        $connection = $this->getConnection();

        try {
            $select = $connection->select()
                ->from(['r' => $this->getTable('directory_country_region')], [])
                ->joinLeft(
                    ['rn' => $this->getTable('directory_country_region_name')],
                    $connection->quoteInto('r.region_id = rn.region_id AND rn.locale = ?', $locale),
                    ['region_name' => 'COALESCE(rn.name, r.default_name)']
                )
                ->where('r.region_id = ?', $regionId);
            $result = $connection->fetchOne($select);
            if ($result !== false) {
                return $result;
            }
        } catch (\Exception) {
            // Locale name table may not exist — fall through to default_name query
        }

        $select = $connection->select()
            ->from($this->getTable('directory_country_region'), 'default_name')
            ->where('region_id = ?', $regionId);

        return $connection->fetchOne($select) ?: null;
    }

    public function getProvinceName(int $provinceId): ?string
    {
        if (!$provinceId) {
            return null;
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable('secomm_giaohangnhanh_province'), 'province_name')
            ->where('province_id = ?', $provinceId);
        return $connection->fetchOne($select) ?: null;
    }

    public function getDistrictName(int $districtId): ?string
    {
        if (!$districtId) {
            return null;
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable('secomm_giaohangnhanh_district'), 'district_name')
            ->where('district_id = ?', $districtId);
        return $connection->fetchOne($select) ?: null;
    }

    public function getWardName(string $wardCode): ?string
    {
        if (!$wardCode) {
            return null;
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable('secomm_giaohangnhanh_ward'), 'ward_name')
            ->where('ward_code = ?', $wardCode);
        return $connection->fetchOne($select) ?: null;
    }

    /**
     * Bulk resolve region_id => default_name for a set of region IDs.
     *
     * @param int[] $regionIds
     * @return array<int, string|null>
     */
    public function getRegionNameMap(array $regionIds): array
    {
        if (empty($regionIds)) {
            return [];
        }
        $regionIds = array_filter(array_map('intval', $regionIds));
        if (empty($regionIds)) {
            return [];
        }

        $connection = $this->getConnection();
        $map = [];
        foreach (array_chunk(array_unique($regionIds), 500) as $chunk) {
            $select = $connection->select()
                ->from($this->getTable('directory_country_region'), ['region_id', 'default_name'])
                ->where('region_id IN (?)', $chunk);
            foreach ($connection->fetchAll($select) as $row) {
                $map[(int)$row['region_id']] = $row['default_name'];
            }
        }
        return $map;
    }

    /**
     * Bulk resolve city_id => display name for a set of city IDs.
     *
     * @param int[] $cityIds
     * @param string $locale
     * @return array<int, string|null>
     */
    public function getCityNameMap(array $cityIds, string $locale = 'vi_VN'): array
    {
        if (empty($cityIds)) {
            return [];
        }
        $cityIds = array_filter(array_map('intval', $cityIds));
        if (empty($cityIds)) {
            return [];
        }

        $connection = $this->getConnection();
        $map = [];
        foreach (array_chunk(array_unique($cityIds), 500) as $chunk) {
            $select = $connection->select()
                ->from(['c' => $this->getTable('directory_region_city')], ['c.city_id'])
                ->joinLeft(
                    ['cn' => $this->getTable('directory_region_city_name')],
                    $connection->quoteInto('c.city_id = cn.city_id AND cn.locale = ?', $locale),
                    ['city_name' => 'COALESCE(cn.name, c.default_name)']
                )
                ->where('c.city_id IN (?)', $chunk);
            foreach ($connection->fetchAll($select) as $row) {
                $map[(int)$row['city_id']] = $row['city_name'];
            }
        }
        return $map;
    }

    /**
     * Bulk resolve province_id => province_name for a set of GHN province IDs.
     *
     * @param int[] $provinceIds
     * @return array<int, string|null>
     */
    public function getProvinceNameMap(array $provinceIds): array
    {
        if (empty($provinceIds)) {
            return [];
        }
        $provinceIds = array_filter(array_map('intval', $provinceIds));
        if (empty($provinceIds)) {
            return [];
        }

        $connection = $this->getConnection();
        $map = [];
        foreach (array_chunk(array_unique($provinceIds), 500) as $chunk) {
            $select = $connection->select()
                ->from($this->getTable('secomm_giaohangnhanh_province'), ['province_id', 'province_name'])
                ->where('province_id IN (?)', $chunk);
            foreach ($connection->fetchAll($select) as $row) {
                $map[(int)$row['province_id']] = $row['province_name'];
            }
        }
        return $map;
    }

    /**
     * Bulk resolve district_id => district_name for a set of GHN district IDs.
     *
     * @param int[] $districtIds
     * @return array<int, string|null>
     */
    public function getDistrictNameMap(array $districtIds): array
    {
        if (empty($districtIds)) {
            return [];
        }
        $districtIds = array_filter(array_map('intval', $districtIds));
        if (empty($districtIds)) {
            return [];
        }

        $connection = $this->getConnection();
        $map = [];
        foreach (array_chunk(array_unique($districtIds), 500) as $chunk) {
            $select = $connection->select()
                ->from($this->getTable('secomm_giaohangnhanh_district'), ['district_id', 'district_name'])
                ->where('district_id IN (?)', $chunk);
            foreach ($connection->fetchAll($select) as $row) {
                $map[(int)$row['district_id']] = $row['district_name'];
            }
        }
        return $map;
    }

    /**
     * Bulk resolve ward_code => ward_name for a set of GHN ward codes.
     *
     * @param string[] $wardCodes
     * @return array<string, string|null>
     */
    public function getWardNameMap(array $wardCodes): array
    {
        if (empty($wardCodes)) {
            return [];
        }
        $wardCodes = array_filter(array_unique($wardCodes), fn($code) => $code !== '');
        if (empty($wardCodes)) {
            return [];
        }

        $connection = $this->getConnection();
        $map = [];
        foreach (array_chunk($wardCodes, 500) as $chunk) {
            $select = $connection->select()
                ->from($this->getTable('secomm_giaohangnhanh_ward'), ['ward_code', 'ward_name'])
                ->where('ward_code IN (?)', $chunk);
            foreach ($connection->fetchAll($select) as $row) {
                $map[$row['ward_code']] = $row['ward_name'];
            }
        }
        return $map;
    }

    /**
     * Get Magento raw location reference data dynamically based on active locale
     *
     * @param string $locale
     * @return array
     */
    public function getMagentoLocationsReference(string $locale = 'vi_VN'): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(['c' => $this->getTable('directory_region_city')], ['city_id'])
            ->joinInner(
                ['cr' => $this->getTable('directory_country_region')],
                'c.region_id = cr.region_id',
                ['country_id', 'region_id', 'region_name' => 'default_name']
            )
            ->joinLeft(
                ['cn' => $this->getTable('directory_region_city_name')],
                $connection->quoteInto('c.city_id = cn.city_id AND cn.locale = ?', $locale),
                ['city_name' => 'COALESCE(cn.name, c.default_name)']
            )
            ->where('cr.country_id LIKE ?', 'VN')
            ->order(['cr.default_name ASC', 'COALESCE(cn.name, c.default_name) ASC']);

        return $connection->fetchAll($select);
    }

    /**
     * Get GHN raw location reference data
     *
     * @return array
     */
    public function getGhnLocationsReference(): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(['p' => $this->getTable('secomm_giaohangnhanh_province')], ['province_id', 'province_name'])
            ->joinInner(
                ['d' => $this->getTable('secomm_giaohangnhanh_district')],
                'p.province_id = d.province_id',
                ['district_id', 'district_name']
            )
            ->joinInner(
                ['w' => $this->getTable('secomm_giaohangnhanh_ward')],
                'd.entity_id = w.district_id',
                ['ward_code', 'ward_name']
            )
            ->order(['p.province_name ASC', 'd.district_name ASC', 'w.ward_name ASC']);

        return $connection->fetchAll($select);
    }
}
