<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Integration\CustomerData;


use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Secomm\AddressDropdown\CustomerData\CityData;

class CityDataTest extends TestCase
{
    /**
     * @var CityData
     */
    private $cityData;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        $this->cityData = $objectManager->get(CityData::class);
        $this->storeManager = $objectManager->get(StoreManagerInterface::class);
    }

    public function testGetSectionData()
    {
        $result = $this->cityData->getSectionData();
        $this->assertIsArray($result);
        $this->assertGreaterThan(0,count($result));
    }
}
