<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Block\Customer\DataProviders;

use Secomm\GiaoHangNhanh\Model\Config;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Class AdditionalConfig
 *
 * @package Secomm\GiaoHangNhanh\Block\Customer\DataProviders
 */
class AdditionalConfig implements ArgumentInterface
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var Config
     */
    private $config;

    /**
     * Constructor
     *
     * @param Config $config
     * @param SerializerInterface $serializer
     */
    public function __construct(
        Config $config,
        SerializerInterface $serializer
    ) {
        $this->config = $config;
        $this->serializer = $serializer;
    }

    /**
     * Get districts
     *
     * @return array
     */
    private function getDistricts()
    {
        $districts = $this->config->getDistricts();
        $data = [];
        foreach ($districts as $district) {
            $data[$district['region_id']][] = [
                'districtName' => $district['district_name'],
                'districtID' => $district['district_id']
            ];
        }
        return $data;
    }

    /**
     * @return string
     */
    public function getJsonData(): string
    {
        return $this->serializer->serialize([
            'districts' => $this->getDistricts()
        ]);
    }
}
