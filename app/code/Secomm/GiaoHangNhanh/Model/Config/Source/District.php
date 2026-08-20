<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Store\Model\Information;
use Magento\Store\Model\StoreManagerInterface;
use Secomm\GiaoHangNhanh\Model\Config;

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
        $districts = $this->config->getDistricts();
        $data = [];
        foreach ($districts as $district) {
            $provinceName = $district['province_name'] ?? '';
            $districtName = $district['district_name'] ?? '';
            $label = $provinceName ? sprintf('%s - %s', $provinceName, $districtName) : $districtName;

            $data[] = [
                'label' => $label,
                'value' => $district['district_id']
            ];
        }

        if ($data) {
            return $data;
        }

        return [['value' => '', 'label' => __('No district to select.')]];
    }
}
