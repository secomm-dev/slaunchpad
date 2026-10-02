<?php
namespace Secomm\AddressDropdown\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Locale\ResolverInterface;
use Secomm\AddressDropdown\Helper\Data as AddressDropdownHelper;
use Secomm\AddressDropdown\Model\Cache\Type as CacheType;
use Secomm\AddressDropdown\Model\ResourceModel\CitySort;

class CityData implements SectionSourceInterface
{
    /**
     * Section cache TTL in seconds (pre-TASK-Z6SK3T behavior kept).
     */
    private const CACHE_LIFETIME = 3600;

    public function __construct(
        protected AddressDropdownHelper $addressDropdownHelper,
        protected ResourceConnection $resource,
        protected CacheType $cacheType,
        protected ResolverInterface $localeResolver,
        protected State $appState,
        protected \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
    }

    public function getSectionData()
    {
        // TASK-SEC-A5: master switch off for this store scope — the section serves no data
        // (mixins read the checkoutConfig flag and never request city-data content anyway).
        if (!$this->addressDropdownHelper->isAddressDropdownModuleEnabled()) {
            return [];
        }

        // TASK-SEC-1.6: the cache id MUST be store-scoped — a global key served store A's
        // region/city dataset to store B (both read and write used one shared key).
        $cacheId = 'city_data_cache_key_' . (int) $this->storeManager->getStore()->getId();

        $cachedData = $this->cacheType->load($cacheId);
        if (is_string($cachedData)) {
            return json_decode($cachedData, true);
        }

        $output = $this->buildSectionData();

        // TASK-Z6SK3T: saved through the tagged type frontend, so `cache:clean
        // secomm_address_city` purges it (untagged saves on the default frontend were
        // only reachable via cache:flush — a 1h stale window after a scheme import).
        $this->cacheType->save(
            json_encode($output),
            $cacheId,
            [CacheType::CACHE_TAG],
            self::CACHE_LIFETIME
        );

        return $output;
    }

    /**
     * Build the region→city dataset in exactly two statements (TASK-Z6SK3T — was one
     * region collection + one city collection per region, 1,191 queries on a cold cache).
     *
     * Per-region shape is unchanged: `name` = default_name, `label` = localized name
     * (Region::getName() falls back to default_name), `city[]` keyed by default_name with
     * default_name/name entries — city `name` stays nullable like the CityLocaleCollection
     * join. Regions without city rows are omitted (data-derived scope): the section
     * consumers already fall back through try/catch when a region entry cannot resolve a
     * city, so dropping the non-VN empty entries is behavior-identical while shrinking the
     * payload from ~1,190 region keys to the ones that carry cities.
     *
     * @return array
     */
    private function buildSectionData(): array
    {
        $connection = $this->resource->getConnection();
        $locale = $this->getLocale();
        $regionTable = $this->resource->getTableName('directory_country_region');
        $regionNameTable = $this->resource->getTableName('directory_country_region_name');
        $cityTable = $this->resource->getTableName('directory_region_city');
        $cityNameTable = $this->resource->getTableName('directory_region_city_name');

        // 1) Regions that actually carry city data, with the localized label.
        $regions = $connection->fetchAll(
            $connection->select()
                ->from(
                    ['r' => $regionTable],
                    [
                        'region_id' => 'r.region_id',
                        'name' => 'r.default_name',
                        'label' => new \Zend_Db_Expr('COALESCE(rn.name, r.default_name)'),
                    ]
                )
                ->joinLeft(
                    ['rn' => $regionNameTable],
                    'r.region_id = rn.region_id AND rn.locale = :region_locale',
                    []
                )
                ->where(
                    'r.region_id IN (?)',
                    new \Zend_Db_Expr('SELECT DISTINCT region_id FROM ' . $cityTable)
                ),
            [':region_locale' => $locale]
        );

        // 2) All cities with the locale-resolved name in one statement. Canonical
        // vi-alphabet sort (TASK-Z6SK3T) via the shared CitySort builder — Đ is its own
        // letter after the full D block (…D, Đ, E…).
        $cities = $connection->fetchAll(
            $connection->select()
                ->from(
                    ['c' => $cityTable],
                    ['region_id' => 'c.region_id', 'default_name' => 'c.default_name', 'name' => 'n.name']
                )
                ->joinLeft(
                    ['n' => $cityNameTable],
                    'c.city_id = n.city_id AND n.locale = :region_locale',
                    []
                )
                ->order(new \Zend_Db_Expr(CitySort::expression('n.name', 'c.default_name', 'c.city_id'))),
            [':region_locale' => $locale]
        );

        $citiesByRegion = [];
        foreach ($cities as $city) {
            $citiesByRegion[$city['region_id']][$city['default_name']] = [
                'default_name' => $city['default_name'],
                'name' => $city['name'],
            ];
        }

        $output = [];
        foreach ($regions as $region) {
            $regionId = (int) $region['region_id'];
            $output[$regionId]['name'] = $region['name'];
            $output[$regionId]['label'] = $region['label'];
            foreach ($citiesByRegion[$regionId] ?? [] as $defaultName => $city) {
                $output[$regionId]['city'][$defaultName] = $city;
            }
        }

        return $output;
    }

    /**
     * Locale rule mirrors CityLocaleCollection::_initSelect: the section is
     * storefront-only, but keep the admin guard so a CLI/admin bootstrap resolves the
     * same names the legacy collection path returned.
     *
     * @return string
     */
    private function getLocale(): string
    {
        if ($this->appState->getAreaCode() === Area::AREA_ADMINHTML) {
            return Resolver::DEFAULT_LOCALE;
        }
        return $this->localeResolver->getLocale();
    }
}
