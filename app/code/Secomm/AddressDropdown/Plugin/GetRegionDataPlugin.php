<?php

namespace Secomm\AddressDropdown\Plugin;

use Magento\Directory\Helper\Data;

class GetRegionDataPlugin
{
    /**
     * @param Data $subject
     * @param array $result
     * @return array
     */
    public function afterGetRegionData(Data $subject, array $result): array
    {
        $config = $result['config'];
        unset($result['config']);
        foreach ($result as $countryCode => $region ) {
            uasort($result[$countryCode], function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        }
        $result['config'] = $config;
        return $result;
    }
}
