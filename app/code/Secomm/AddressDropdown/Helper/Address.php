<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Helper;

use Exception;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\Resolver;
use Magento\Customer\Model\AddressFactory;
use Magento\Framework\Locale\ResolverInterface;
use Secomm\AddressDropdown\Model\CityModelFactory;
use Magento\Framework\App\State;

class Address extends AbstractHelper
{

    protected $locale;


    public function __construct(
        Context $context,
        protected ResourceConnection $resource,
        protected AddressFactory $addressFactory,
        protected CityModelFactory $cityFactory,
        protected ResolverInterface $localeResolver,
        protected Resolver $resolver,
        protected State $appState
    )
    {
        parent::__construct($context);
    }

    /**
     * @param $addressId
     * @return \Magento\Customer\Model\Address
     */
    public function getAddressObjById($addressId): \Magento\Customer\Model\Address
    {
        return $this->addressFactory->create()->load($addressId);
    }


    /**
     * Sub City Name by ID City
     *
     * @param $defaultName
     * @param null $cityDefaultName
     * @param null $locale
     * @return array|mixed
     */
    public function getSubCityNameByDefaultName($defaultName, $cityDefaultName = null, $locale = null): mixed
    {
        $adapter = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('directory_city_sub_city');
        $tableNameCity = $this->resource->getTableName('directory_region_city');

        // Map with directory_city_sub_city_name
        $select = $adapter->select()
            ->from(['d' => $tableName], [])  // Do not select any columns from the main table
            ->joinLeft(
                ['n' => $this->resource->getTableName('directory_city_sub_city_name')],
                "n.sub_city_id = d.sub_city_id",
                ['name']  // Select the 'name' column from the joined table
            )
            ->where('d.default_name = ?', $defaultName);  // Alias 'd' used for clarity

        if ($cityDefaultName) {
            $select->joinInner(
                    ['city' => $tableNameCity],
                    "d.city_id = city.city_id AND city.default_name LIKE '$cityDefaultName'",
                    []
                );
        }

        if (!$locale) {
            $locale = $this->getLocale();
        }
        $select->where('n.locale = ?', $locale)->limit(1);

        if ($adapter->fetchOne($select)) {
            return $adapter->fetchOne($select);
        } else {
            return $defaultName;
        }
    }

    public function getCityNameByDefaultName($defaultName, $regionId = null, $locale = null): mixed
    {
        $adapter = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('directory_region_city');

        // Map with directory_region_city_name
        $select = $adapter->select()
            ->from(['d' => $tableName], [])  // Do not select any columns from the main table
            ->joinLeft(
                ['n' => $this->resource->getTableName('directory_region_city_name')],
                "n.city_id = d.city_id",
                ['name']  // Select the 'name' column from the joined table
            )
            ->where('d.default_name = ?', $defaultName);  // Alias 'd' used for clarity
        if (!is_null($regionId)) {
                $select->where('d.region_id = ?', $regionId);
        }

        if (!$locale) {
            $locale = $this->getLocale();
        }
        $select->where('n.locale = ?', $locale)->limit(1);
        if ($adapter->fetchOne($select)){
            return $adapter->fetchOne($select);
        }else{
            return $defaultName;
        }
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function getLocale(): string
    {
        if ($this->getCurrentAreaCode() === \Magento\Framework\App\Area::AREA_ADMINHTML) {
            $this->locale = $this->localeResolver->getLocale();
        } else {
            $this->locale = $this->resolver->getLocale();
        }
        return $this->locale;
    }

    /**
     * @param $id
     * @return string
     */
    public function getCityName($id): string
    {
        try {
            $city = $this->cityFactory->create()->load($id);
            return (string)$city->getDefaultName();
        }catch (Exception $exception) {
            return '';
        }
    }

    /**
     * @return array
     */
    public function getCityData() : array
    {
        $adapter = $this->resource->getConnection();
        $table = $this->resource->getTableName('directory_region_city');
        $tableName = $this->resource->getTableName('directory_region_city_name');
        $select = $adapter->select()
            ->from(['m' => $table],'*')
            ->joinLeft(
                ['n' => $tableName],
                "m.city_id = n.city_id AND n.locale = '".$this->getLocale()."'",
                ['n.name']
            )->order('n.name ASC');
        $cities = $adapter->fetchAll($select);

        if (count($cities) > 0) {
            return $cities;
        }
        return [];
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function getCurrentAreaCode(): string
    {
        return $this->appState->getAreaCode();
    }

}
