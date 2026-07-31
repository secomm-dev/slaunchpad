<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Config\Source;

use Secomm\GiaoHangNhanh\Model\Config;
use Magento\Framework\Option\ArrayInterface;
use Magento\Store\Model\Information;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class District
 *
 * @package Secomm\GiaoHangNhanh\Model\Config\Source
 */
class District implements ArrayInterface
{
    /**
     * @var Information
     */
    private $storeInformation;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * District constructor.
     * @param Config $config
     * @param Information $storeInformation
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Config $config,
        Information $storeInformation,
        StoreManagerInterface $storeManager
    ) {
        $this->storeInformation = $storeInformation;
        $this->storeManager = $storeManager;
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        $store = $this->storeManager->getStore();
        $storeInfo = $this->storeInformation->getStoreInformationObject($store);
        $districts = $this->config->getDistricts();
        $data = [];
        $allData = [];

        $storeRegionId = $storeInfo->getRegionId();

        foreach ($districts as $district) {
            $option = [
                'label' => $district['district_name'],
                'value' => $district['district_id']
            ];
            $allData[] = $option;

            if ($storeRegionId && $district['region_id'] == $storeRegionId) {
                $data[] = $option;
            }
        }

        if ($data) {
            return $data;
        }

        // Fallback: Show all districts if store region isn't selected or didn't match GHN regions
        return $allData ?: [['value' => '', 'label' => __('No district to select.')]];
    }
}
