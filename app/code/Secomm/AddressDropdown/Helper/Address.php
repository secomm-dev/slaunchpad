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
use Secomm\AddressDropdown\Model\ResourceModel\CitySort;
use Secomm\AddressDropdown\Model\CityModelFactory;
use Magento\Framework\App\State;

class Address extends AbstractHelper
{

    protected $locale;

    /**
     * TASK-Z6SK3T: per-request memo for getCityNameByDefaultName (helpers are DI
     * singletons per request). The renderer/default-address plugins resolve the same
     * city repeatedly within one request.
     */
    private array $cityNameMemo = [];


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


    public function getCityNameByDefaultName($defaultName, $regionId = null, $locale = null): mixed
    {
        if (!$locale) {
            $locale = $this->getLocale();
        }

        $memoKey = $defaultName . '|' . ($regionId ?? '*') . '|' . $locale;
        if (array_key_exists($memoKey, $this->cityNameMemo)) {
            return $this->cityNameMemo[$memoKey];
        }

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

        // TASK-Z6SK3T: deterministic tie-break — duplicated ward names within one region
        // (vd "Thanh An" ×2, TASK-YQSS3M) previously resolved arbitrarily under limit(1).
        $select->where('n.locale = ?', $locale)->order('d.city_id ASC')->limit(1);

        // TASK-Z6SK3T: single execution (was run twice for one result) + explicit miss
        // check (the old truthiness check also treated falsy names as misses).
        $name = $adapter->fetchOne($select);
        $resolved = ($name !== false && $name !== null) ? $name : $defaultName;
        $this->cityNameMemo[$memoKey] = $resolved;

        return $resolved;
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
                // TASK-SEC-A1: locale is a config-derived value — still quoted, never concat raw.
                'm.city_id = n.city_id AND n.locale = ' . $adapter->quote($this->getLocale()),
                ['n.name']
            // Canonical vi-alphabet sort (TASK-Z6SK3T): shared CitySort builder —
            // mirrors CityLocaleCollection / LocationHierarchyProvider.
            )->order(new \Zend_Db_Expr(CitySort::expression('n.name', 'm.default_name', 'm.city_id')));
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
