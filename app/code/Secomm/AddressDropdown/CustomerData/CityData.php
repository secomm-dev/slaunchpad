<?php
namespace Secomm\AddressDropdown\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Framework\App\Cache\Frontend\Pool as CacheFrontendPool;
use Magento\Framework\App\Cache\TypeListInterface;
use Secomm\AddressDropdown\Helper\Data as AddressDropdownHelper;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollectionFactory as CityCollectionFactory;

class CityData implements SectionSourceInterface
{

    public function __construct(
        protected AddressDropdownHelper    $addressDropdownHelper,
        protected CityCollectionFactory    $cityCollectionFactory,
        protected RegionCollectionFactory  $regionCollectionFactory,
        protected TypeListInterface        $cacheTypeList,
        protected CacheFrontendPool        $cacheFrontendPool,
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
        $cacheFrontend = $this->cacheFrontendPool->get('default');

        $cachedData = $cacheFrontend->load($cacheId);
        if ($cachedData !== false) {
            return json_decode($cachedData, true);
        }

        $regionCollection = $this->regionCollectionFactory->create();
        $regions = $regionCollection->getItems();

        $output = [];
        foreach ($regions as $region) {
            $regionId = $region->getId();
            $output[$regionId]['name'] = $region->getDefaultName();
            $output[$regionId]['label'] = $region->getName();

            $cityCollectionFactory = $this->cityCollectionFactory->create();
            $cityCollectionFactory->addFieldToFilter('region_id', $regionId);
            $cities = $cityCollectionFactory->getItems();

            if (empty($cities)) {
                continue;
            }
            foreach ($cities as $city) {
                $output[$regionId]['city'][$city->getDefaultName()]['default_name'] = $city->getDefaultName();
                $output[$regionId]['city'][$city->getDefaultName()]['name'] = $city->getName();
            }
        }

        $cacheFrontend->save(json_encode($output), $cacheId, [], 3600);

        return $output;
    }
}
