<?php
namespace Secomm\AddressDropdown\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Framework\App\Cache\Frontend\Pool as CacheFrontendPool;
use Magento\Framework\App\Cache\TypeListInterface;
use Secomm\AddressDropdown\Helper\Address as AddressHelper;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollectionFactory as CityCollectionFactory;
use Secomm\AddressDropdown\Model\ResourceModel\SubCityModel\SubCityLocaleCollectionFactory as SubCityCollectionFactory;

class CityData implements SectionSourceInterface
{

    public function __construct(
        protected AddressHelper            $addressHelper,
        protected CityCollectionFactory    $cityCollectionFactory,
        protected SubCityCollectionFactory $subCityCollectionFactory,
        protected RegionCollectionFactory  $regionCollectionFactory,
        protected TypeListInterface        $cacheTypeList,
        protected CacheFrontendPool        $cacheFrontendPool
    )
    {
    }

    public function getSectionData()
    {
        $cacheId = 'city_data_cache_key';
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

                // Fetch subcities for the city
                $subCityCollectionFactory = $this->subCityCollectionFactory->create();
                $subCityCollectionFactory->addFieldToFilter('city_id', $city->getId());
                $subCities = $subCityCollectionFactory->getItems();

                if (empty($subCities)) {
                    continue;
                }
                foreach ($subCities as $subCity) {
                    $output[$regionId]['city'][$city->getDefaultName()]['sub_city'][$subCity->getDefaultName()]['name'] = $subCity->getName();
                    $output[$regionId]['city'][$city->getDefaultName()]['sub_city'][$subCity->getDefaultName()]['default_name'] = $subCity->getDefaultName();
                }
            }
        }

        $cacheFrontend->save(json_encode($output), $cacheId, [], 3600);

        return $output;
    }
}
